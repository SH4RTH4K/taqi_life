<?php
/**
 * TAQI LIFE database configuration and SQL import utility.
 * Remove or rename this file after use. It can update database-config.php,
 * which is read by wp-config.php on the next request.
 */

session_start();

function taqi_split_sql( $sql ) {
    $statements = array();
    $buffer = '';
    $length = strlen( $sql );
    $quote = '';
    $line_comment = false;
    $block_comment = false;

    for ( $i = 0; $i < $length; $i++ ) {
        $char = $sql[ $i ];
        $next = $i + 1 < $length ? $sql[ $i + 1 ] : '';

        if ( $line_comment ) {
            $buffer .= $char;
            if ( "\n" === $char ) {
                $line_comment = false;
            }
            continue;
        }
        if ( $block_comment ) {
            $buffer .= $char;
            if ( '*' === $char && '/' === $next ) {
                $buffer .= $next;
                $i++;
                $block_comment = false;
            }
            continue;
        }
        if ( '' !== $quote ) {
            $buffer .= $char;
            if ( '\\' === $char && $i + 1 < $length ) {
                $buffer .= $sql[ ++$i ];
            } elseif ( $char === $quote ) {
                $quote = '';
            }
            continue;
        }
        if ( ( '#' === $char ) || ( '-' === $char && '-' === $next && ( $i + 2 >= $length || ctype_space( $sql[ $i + 2 ] ) ) ) ) {
            $buffer .= $char;
            if ( '-' === $char ) {
                $buffer .= $next;
                $i++;
            }
            $line_comment = true;
            continue;
        }
        if ( '/' === $char && '*' === $next ) {
            $buffer .= $char . $next;
            $i++;
            $block_comment = true;
            continue;
        }
        if ( "'" === $char || '"' === $char || '`' === $char ) {
            $quote = $char;
            $buffer .= $char;
            continue;
        }
        if ( ';' === $char ) {
            if ( '' !== trim( $buffer ) ) {
                $statements[] = trim( $buffer );
            }
            $buffer = '';
            continue;
        }
        $buffer .= $char;
    }
    if ( '' !== trim( $buffer ) ) {
        $statements[] = trim( $buffer );
    }
    return $statements;
}

function taqi_json( $data ) {
    header( 'Content-Type: application/json; charset=utf-8' );
    echo wp_json_encode( $data );
    exit;
}

// This file is standalone, so use a small JSON encoder fallback outside WordPress.
if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data ) {
        return json_encode( $data );
    }
}

$message = '';
$error   = '';
$defaults = array(
    'host' => getenv( 'TAQI_DB_HOST' ) ?: 'localhost',
    'name' => getenv( 'TAQI_DB_NAME' ) ?: 'taqilife_wp228',
    'user' => getenv( 'TAQI_DB_USER' ) ?: 'taqilife_wp228',
);

if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && 'start' === ( $_POST['action'] ?? '' ) ) {
    $host     = trim( (string) ( $_POST['db_host'] ?? '' ) );
    $name     = trim( (string) ( $_POST['db_name'] ?? '' ) );
    $user     = trim( (string) ( $_POST['db_user'] ?? '' ) );
    $password = (string) ( $_POST['db_password'] ?? '' );
    $sql_file = $_FILES['sql_file'] ?? null;

    if ( '' === $host || '' === $name || '' === $user ) {
        $error = 'Database host, name, and user are required.';
    } elseif ( ! preg_match( '/^[A-Za-z0-9_.-]+$/', $name ) ) {
        $error = 'Database name contains unsupported characters.';
    } elseif ( ! $sql_file || UPLOAD_ERR_OK !== (int) $sql_file['error'] ) {
        $error = 'Choose a valid .sql file to import.';
    } elseif ( 'sql' !== strtolower( pathinfo( $sql_file['name'], PATHINFO_EXTENSION ) ) ) {
        $error = 'Only .sql files are accepted.';
    } else {
        $sql = file_get_contents( $sql_file['tmp_name'] );
        if ( false === $sql || '' === trim( $sql ) ) {
            $error = 'The selected SQL file is empty or could not be read.';
        } else {
            mysqli_report( MYSQLI_REPORT_OFF );
            $db = @new mysqli( $host, $user, $password, $name );
            if ( $db->connect_errno ) {
                $error = 'Database connection failed: ' . $db->connect_error;
            } else {
                $statements = taqi_split_sql( $sql );
                $job_id = bin2hex( random_bytes( 16 ) );
                $job_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'taqi-db-' . session_id() . '-' . $job_id . '.json';
                $job = array( 'host' => $host, 'name' => $name, 'user' => $user, 'password' => $password, 'statements' => $statements, 'position' => 0 );
                if ( false === file_put_contents( $job_file, json_encode( $job ), LOCK_EX ) ) {
                    $error = 'Could not create the temporary import job.';
                } else {
                    $db->close();
                    taqi_json( array( 'ok' => true, 'job' => $job_id, 'total' => count( $statements ) ) );
                }
                $db->close();
            }
        }
    }
    taqi_json( array( 'ok' => false, 'error' => $error ) );
}

if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && 'step' === ( $_POST['action'] ?? '' ) ) {
    $job_id = preg_replace( '/[^a-f0-9]/', '', (string) ( $_POST['job'] ?? '' ) );
    $job_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'taqi-db-' . session_id() . '-' . $job_id . '.json';
    if ( ! $job_id || ! is_readable( $job_file ) ) {
        taqi_json( array( 'ok' => false, 'error' => 'Import session expired. Please start again.' ) );
    }
    $job = json_decode( file_get_contents( $job_file ), true );
    $db = @new mysqli( $job['host'], $job['user'], $job['password'], $job['name'] );
    if ( $db->connect_errno ) {
        taqi_json( array( 'ok' => false, 'error' => 'Database connection failed: ' . $db->connect_error ) );
    }
    $batch = 10;
    $end = min( count( $job['statements'] ), $job['position'] + $batch );
    $last_table = '';
    for ( $i = $job['position']; $i < $end; $i++ ) {
        if ( preg_match( '/(?:CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|INSERT\s+INTO|ALTER\s+TABLE|DROP\s+TABLE)\s+[`]?([^`\s(]+)[`]?/i', $job['statements'][ $i ], $table_match ) ) {
            $last_table = $table_match[1];
        }
        if ( ! $db->query( $job['statements'][ $i ] ) ) {
            $db->close();
            taqi_json( array( 'ok' => false, 'error' => 'Statement ' . ( $i + 1 ) . ' failed: ' . $db->error ) );
        }
        if ( $result = $db->store_result() ) {
            $result->free();
        }
    }
    $job['position'] = $end;
    $done = $end >= count( $job['statements'] );
    if ( $done ) {
        $config = "<?php\n// Generated by database-import.php. Keep this file private.\n" .
            "\$taqi_db_host = " . var_export( $job['host'], true ) . ";\n" .
            "\$taqi_db_name = " . var_export( $job['name'], true ) . ";\n" .
            "\$taqi_db_user = " . var_export( $job['user'], true ) . ";\n" .
            "\$taqi_db_password = " . var_export( $job['password'], true ) . ";\n";
        if ( false === file_put_contents( __DIR__ . '/database-config.php', $config, LOCK_EX ) ) {
            $db->close();
            taqi_json( array( 'ok' => false, 'error' => 'Import completed, but database-config.php could not be written.' ) );
        }
        @unlink( $job_file );
    } else {
        file_put_contents( $job_file, json_encode( $job ), LOCK_EX );
    }
    $db->close();
    taqi_json( array( 'ok' => true, 'done' => $done, 'position' => $end, 'total' => count( $job['statements'] ), 'table' => $last_table ) );
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TAQI LIFE Database Import</title>
<style>body{font:16px system-ui,sans-serif;background:#f3f4f6;margin:0;padding:32px;color:#172033}.card{max-width:620px;margin:auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 8px 30px #0001}label{display:block;font-weight:600;margin:16px 0 6px}input{box-sizing:border-box;width:100%;padding:10px;border:1px solid #ccd2dc;border-radius:6px}button{margin-top:22px;background:#1769e0;color:#fff;border:0;border-radius:6px;padding:11px 18px;font-weight:700}.notice{padding:12px;border-radius:6px;margin:16px 0}.ok{background:#e4f7ec;color:#126b3a}.bad{background:#fde8e8;color:#9b1c1c}.warning{font-size:14px;color:#755400;background:#fff8df;padding:12px;border-radius:6px}</style></head>
<body><main class="card"><h1>Database import</h1><p class="warning">This utility writes database credentials to <code>database-config.php</code>. Delete or rename <code>database-import.php</code> after setup.</p>
<?php if ( $message ) : ?><div class="notice ok"><?php echo htmlspecialchars( $message, ENT_QUOTES, 'UTF-8' ); ?></div><?php endif; ?>
<?php if ( $error ) : ?><div class="notice bad"><?php echo htmlspecialchars( $error, ENT_QUOTES, 'UTF-8' ); ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data"><label for="db_host">Database host</label><input id="db_host" name="db_host" value="<?php echo htmlspecialchars( $_POST['db_host'] ?? $defaults['host'], ENT_QUOTES, 'UTF-8' ); ?>" required>
<label for="db_name">Database name</label><input id="db_name" name="db_name" value="<?php echo htmlspecialchars( $_POST['db_name'] ?? $defaults['name'], ENT_QUOTES, 'UTF-8' ); ?>" required>
<label for="db_user">Database user</label><input id="db_user" name="db_user" value="<?php echo htmlspecialchars( $_POST['db_user'] ?? $defaults['user'], ENT_QUOTES, 'UTF-8' ); ?>" required>
<label for="db_password">Database password</label><input id="db_password" type="password" name="db_password" autocomplete="new-password">
<label for="sql_file">SQL dump</label><input id="sql_file" type="file" name="sql_file" accept=".sql,text/sql" required><button type="submit">Test connection and import database</button></form>
<section id="progress" hidden><p><strong id="progress-label">Preparing import…</strong></p><progress id="progress-bar" value="0" max="100" style="width:100%"></progress><p id="progress-detail"></p></section>
<script>
const form=document.querySelector('form'), progress=document.querySelector('#progress'), bar=document.querySelector('#progress-bar'), label=document.querySelector('#progress-label'), detail=document.querySelector('#progress-detail');
form.addEventListener('submit', async function(event){
  event.preventDefault(); form.querySelector('button').disabled=true; progress.hidden=false;
  const data=new FormData(form); data.append('action','start');
  try {
    const start=await fetch(location.href,{method:'POST',body:data}); const job=await start.json();
    if(!job.ok) throw new Error(job.error||'Could not start import.');
    bar.max=job.total; label.textContent='Importing database…'; detail.textContent='0 of '+job.total+' statements completed';
    let done=false;
    while(!done){
      const step=await fetch(location.href,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'step',job:job.job})});
      const result=await step.json(); if(!result.ok) throw new Error(result.error||'Import failed.');
      bar.value=result.position; detail.textContent=(result.table?'Table '+result.table+' — ':'')+result.position+' of '+result.total+' statements completed'; done=result.done;
    }
    label.textContent='Import completed successfully.'; detail.textContent='Database settings were saved. You can now open the WordPress site.';
  } catch(error) { label.textContent='Import failed'; detail.textContent=error.message; }
  form.querySelector('button').disabled=false;
});
</script></main></body></html>
