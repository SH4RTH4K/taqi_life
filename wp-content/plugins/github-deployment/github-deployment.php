<?php
/**
 * Plugin Name: GitHub Deployment
 * Description: Safe GitHub repository checks and deployment workflow for the local application.
 * Version: 1.0.0
 * Author: TAQI LIFE
 */

defined( 'ABSPATH' ) || exit;

final class TAQI_GitHub_Deployment {
    private const SETTINGS_OPTION = 'taqi_github_deployment_settings';
    private const STATUS_OPTION   = 'taqi_github_deployment_status';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
    }

    public function admin_menu() {
        add_menu_page( 'GitHub Deployment', 'GitHub Deployment', 'manage_options', 'github-deployment', array( $this, 'page' ), 'dashicons-admin-site-alt3', 57 );
    }

    private function settings() {
        return wp_parse_args(
            get_option( self::SETTINGS_OPTION, array() ),
            array(
                'enabled'        => 'yes',
                'repository_url' => 'https://github.com/SH4RTH4K/taqi_life.git',
                'branch'         => 'main',
                'remote_name'    => 'origin',
            )
        );
    }

    private function git( $args, $allow_failure = true ) {
        if ( ! function_exists( 'exec' ) ) {
            return array( 'code' => 1, 'output' => 'PHP exec() is disabled on this server.' );
        }
        $command = 'git';
        foreach ( (array) $args as $arg ) {
            $command .= ' ' . escapeshellarg( (string) $arg );
        }
        $previous = getcwd();
        @chdir( untrailingslashit( ABSPATH ) );
        $output = array();
        $code   = 1;
        exec( $command . ' 2>&1', $output, $code );
        if ( false !== $previous ) {
            @chdir( $previous );
        }
        $result = array( 'code' => (int) $code, 'output' => trim( implode( "\n", $output ) ) );
        if ( ! $allow_failure && 0 !== $result['code'] ) {
            $result['output'] = $result['output'] ? $result['output'] : 'Git command failed.';
        }
        return $result;
    }

    private function normalize_url( $url ) {
        $url = preg_replace( '#^https://github\.com/#', 'github:', rtrim( trim( (string) $url ), '/' ) );
        $url = preg_replace( '#^git@github\.com:#', 'github:', $url );
        return rtrim( strtolower( $url ), '.git' );
    }

    private function status( $fetch = false ) {
        $settings = $this->settings();
        $base = array( 'status' => 'Not configured', 'message' => 'Enable GitHub Deployment and save the repository settings.', 'local' => '', 'remote' => '', 'branch' => $settings['branch'], 'commits' => array(), 'changes' => array() );
        if ( 'yes' !== $settings['enabled'] ) {
            return $base;
        }
        if ( ! preg_match( '#^https://github\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+(?:\.git)?$#', $settings['repository_url'] ) ) {
            $base['status'] = 'Configuration error';
            $base['message'] = 'Enter a valid public GitHub repository URL.';
            return $base;
        }
        $inside = $this->git( array( 'rev-parse', '--is-inside-work-tree' ) );
        if ( 0 !== $inside['code'] || 'true' !== trim( $inside['output'] ) ) {
            $base['status'] = 'Git unavailable';
            $base['message'] = 'This installation is not inside a Git working tree.';
            return $base;
        }
        $remote = $this->git( array( 'remote', 'get-url', $settings['remote_name'] ) );
        if ( 0 !== $remote['code'] || $this->normalize_url( $remote['output'] ) !== $this->normalize_url( $settings['repository_url'] ) ) {
            $base['status'] = 'Repository mismatch';
            $base['message'] = 'The configured repository does not match the local remote.';
            return $base;
        }
        $branch = trim( $this->git( array( 'branch', '--show-current' ) )['output'] );
        $local  = trim( $this->git( array( 'rev-parse', 'HEAD' ) )['output'] );
        $base['branch'] = $branch;
        $base['local']  = $local;
        if ( $fetch ) {
            $fetched = $this->git( array( 'fetch', '--prune', $settings['remote_name'], $settings['branch'] ) );
            if ( 0 !== $fetched['code'] ) {
                $base['status'] = 'Connection failed';
                $base['message'] = 'GitHub fetch failed: ' . $fetched['output'];
                return $base;
            }
        }
        $remote_ref = $settings['remote_name'] . '/' . $settings['branch'];
        $target = $this->git( array( 'rev-parse', '--verify', $remote_ref ) );
        if ( 0 !== $target['code'] ) {
            $base['status'] = 'Branch not found';
            $base['message'] = 'The configured branch was not found on GitHub.';
            return $base;
        }
        $base['remote'] = trim( $target['output'] );
        $changes = $this->git( array( 'status', '--porcelain', '--untracked-files=no' ) );
        $base['changes'] = array_values( array_filter( preg_split( '/\R/', trim( $changes['output'] ) ) ) );
        $ahead  = trim( $this->git( array( 'rev-list', '--count', 'HEAD..' . $remote_ref ) )['output'] );
        $behind = trim( $this->git( array( 'rev-list', '--count', $remote_ref . '..HEAD' ) )['output'] );
        $log = $this->git( array( 'log', '--format=%h%x1f%an%x1f%aI%x1f%s', 'HEAD..' . $remote_ref, '-n', '20' ) );
        foreach ( array_filter( preg_split( '/\R/', trim( $log['output'] ) ) ) as $line ) {
            $parts = explode( "\x1f", $line, 4 );
            if ( 4 === count( $parts ) ) {
                $base['commits'][] = array( 'short' => $parts[0], 'author' => $parts[1], 'date' => $parts[2], 'subject' => $parts[3] );
            }
        }
        if ( $branch !== $settings['branch'] ) {
            $base['status'] = 'Wrong local branch';
            $base['message'] = 'The local checkout is on ' . ( $branch ? $branch : 'detached HEAD' ) . ', but the configured branch is ' . $settings['branch'] . '.';
        } elseif ( $base['changes'] ) {
            $base['status'] = 'Local changes detected';
            $base['message'] = 'Tracked local changes must be reviewed before deployment.';
        } elseif ( (int) $ahead > 0 && (int) $behind > 0 ) {
            $base['status'] = 'Diverged branch';
            $base['message'] = 'The local branch and GitHub branch have different history.';
        } elseif ( (int) $ahead > 0 ) {
            $base['status'] = 'Update available';
            $base['message'] = $ahead . ' commit(s) are available on GitHub.';
        } else {
            $base['status'] = 'Up to date';
            $base['message'] = 'The local checkout matches the configured GitHub branch.';
        }
        return $base;
    }

    public function page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $settings = $this->settings();
        $message  = '';
        $error    = '';
        if ( ! empty( $_POST['github_deployment_action'] ) ) {
            check_admin_referer( 'github_deployment_action', 'github_deployment_nonce' );
            $action = sanitize_key( wp_unslash( $_POST['github_deployment_action'] ) );
            if ( 'save' === $action ) {
                $repository = esc_url_raw( trim( wp_unslash( $_POST['repository_url'] ?? '' ) ) );
                $branch = sanitize_text_field( trim( wp_unslash( $_POST['branch'] ?? 'main' ) ) );
                $remote = sanitize_text_field( trim( wp_unslash( $_POST['remote_name'] ?? 'origin' ) ) );
                if ( ! preg_match( '#^https://github\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+(?:\.git)?$#', $repository ) || ! preg_match( '/^[A-Za-z0-9._-]{1,100}$/', $branch ) || ! preg_match( '/^[A-Za-z0-9._-]{1,50}$/', $remote ) ) {
                    $error = 'Enter a valid public GitHub URL, branch, and remote name.';
                } else {
                    update_option( self::SETTINGS_OPTION, array( 'enabled' => ! empty( $_POST['enabled'] ) ? 'yes' : 'no', 'repository_url' => $repository, 'branch' => $branch, 'remote_name' => $remote ), false );
                    $settings = $this->settings();
                    $message = 'GitHub Deployment settings saved.';
                }
            } elseif ( 'check' === $action ) {
                $status = $this->status( true );
                update_option( self::STATUS_OPTION, $status, false );
                $message = 'GitHub status refreshed.';
                if ( in_array( $status['status'], array( 'Connection failed', 'Git unavailable', 'Repository mismatch', 'Configuration error', 'Branch not found' ), true ) ) {
                    $error = $status['message'];
                }
            }
        }
        $settings = $this->settings();
        $status = get_option( self::STATUS_OPTION, array() );
        if ( ! is_array( $status ) || empty( $status['status'] ) ) {
            $status = $this->status();
        }
        $warning_statuses = array( 'Local changes detected', 'Diverged branch', 'Wrong local branch' );
        ?>
        <div class="wrap taqi-github-deployment"><style>
        .taqi-github-deployment{max-width:1180px;padding-bottom:90px}.taqi-github-deployment .gdp-hero{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:24px 26px;margin:16px 0 20px;background:linear-gradient(135deg,#172033,#263d68);border-radius:12px;color:#fff;box-shadow:0 5px 16px #17203326}.taqi-github-deployment .gdp-hero-main{display:flex;align-items:center;gap:15px}.taqi-github-deployment .gdp-hero-mark{display:flex;align-items:center;justify-content:center;width:48px;height:48px;border:1px solid #ffffff45;border-radius:12px;background:#ffffff14;font-size:25px;font-weight:700}.taqi-github-deployment .gdp-hero h1{margin:2px 0 5px;color:#fff;font-size:26px;line-height:1.15}.taqi-github-deployment .gdp-hero p{margin:0;color:#dbe6fa;font-size:13px}.taqi-github-deployment .gdp-eyebrow{color:#a9c8ff;font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase}.taqi-github-deployment .gdp-hero-status{flex:0 0 auto;text-align:right}.taqi-github-deployment .gdp-hero-status-label{display:inline-flex;align-items:center;gap:7px;padding:7px 11px;border:1px solid #ffffff38;border-radius:999px;background:#ffffff12;color:#fff;font-size:12px;font-weight:600}.taqi-github-deployment .gdp-hero-status-label:before{content:"";width:7px;height:7px;border-radius:50%;background:#f4c44e}.taqi-github-deployment .gdp-hero-status-label.is-good:before{background:#5de092}.taqi-github-deployment .gdp-hero-status-label.is-error:before{background:#ff8b8b}.taqi-github-deployment .gdp-box{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:22px;margin:18px 0;box-shadow:0 2px 5px #0000000a}.taqi-github-deployment .gdp-box-header{display:flex;align-items:flex-start;justify-content:space-between;gap:15px;margin-bottom:18px}.taqi-github-deployment .gdp-box-header h2{margin:0 0 4px;font-size:18px}.taqi-github-deployment .gdp-box-header p{margin:0}.taqi-github-deployment .gdp-box-kicker{color:#646970;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em}.taqi-github-deployment .gdp-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:15px}.taqi-github-deployment .gdp-field label{display:block;font-weight:600;font-size:12px;color:#3c434a}.taqi-github-deployment .gdp-field input:not([type=checkbox]){width:100%;box-sizing:border-box;margin-top:6px;min-height:36px}.taqi-github-deployment .gdp-field input[type=checkbox]{width:16px;height:16px;margin:0;vertical-align:middle}.taqi-github-deployment .gdp-field:first-child{display:flex;align-items:flex-end}.taqi-github-deployment .gdp-field:first-child label{display:flex;align-items:center;gap:8px;min-height:36px}.taqi-github-deployment .gdp-wide{grid-column:1/-1}.taqi-github-deployment .gdp-muted{color:#646970;font-size:12px}.taqi-github-deployment .gdp-actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:16px}.taqi-github-deployment .gdp-actions .button{min-height:34px;padding:0 14px;line-height:32px;border-radius:6px}.taqi-github-deployment .gdp-status{padding:15px 17px;border-left:4px solid #2271b1;background:#f6f7f7;border-radius:0 7px 7px 0;margin:16px 0}.taqi-github-deployment .gdp-status strong{display:block;font-size:14px;margin-bottom:4px}.taqi-github-deployment .gdp-status p{margin:3px 0 0}.taqi-github-deployment .gdp-good{border-left-color:#008a20;background:#f0f8f2}.taqi-github-deployment .gdp-warn{border-left-color:#dba617;background:#fff8e5}.taqi-github-deployment .gdp-progress{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:0 0 18px}.taqi-github-deployment .gdp-progress-item{display:flex;align-items:center;gap:8px;color:#646970;font-size:11px;font-weight:600}.taqi-github-deployment .gdp-progress-item span{display:flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:#edf0f2;color:#646970}.taqi-github-deployment .gdp-progress-item.is-current span{background:#2271b1;color:#fff}.taqi-github-deployment .gdp-progress-item.is-current{color:#1d2327}.taqi-github-deployment .gdp-workflow{border:1px solid #dcdcde;border-radius:8px;overflow:hidden}.taqi-github-deployment .gdp-step{display:grid;grid-template-columns:34px 1fr;gap:12px;padding:17px 16px;border-bottom:1px solid #e2e4e7}.taqi-github-deployment .gdp-step:last-child{border-bottom:0}.taqi-github-deployment .gdp-number{width:28px;height:28px;line-height:28px;text-align:center;border-radius:50%;background:#e5f0f7;color:#2271b1;font-weight:700}.taqi-github-deployment .gdp-step h3{margin:2px 0 5px;font-size:15px}.taqi-github-deployment .gdp-step p{margin:0}.taqi-github-deployment .gdp-commits{width:100%;border-collapse:collapse;margin-top:14px}.taqi-github-deployment .gdp-commits th,.taqi-github-deployment .gdp-commits td{padding:8px;text-align:left;border-bottom:1px solid #e2e4e7;font-size:12px}.taqi-github-deployment .gdp-note{padding:11px 13px;background:#fff8e5;color:#6b4e00;border-radius:6px;font-size:12px;line-height:1.5;margin-top:14px}@media(max-width:760px){.taqi-github-deployment .gdp-hero{align-items:flex-start;flex-direction:column}.taqi-github-deployment .gdp-hero-status{text-align:left}.taqi-github-deployment .gdp-grid{grid-template-columns:1fr}.taqi-github-deployment .gdp-wide{grid-column:auto}.taqi-github-deployment .gdp-progress{grid-template-columns:repeat(2,1fr);row-gap:10px}}
        </style>
        <style>
            .taqi-github-deployment>h1,.taqi-github-deployment>h1+p{display:none}.taqi-github-deployment>.gdp-box>h2{margin:0 0 5px!important;font-size:18px}.taqi-github-deployment>.gdp-box>h2+p{margin:0 0 18px}.taqi-github-deployment>.gdp-box:nth-of-type(2)>h2:before{content:'CONTROLLED RELEASE PATH';display:block;margin-bottom:6px;color:#646970;font-size:10px;font-weight:700;letter-spacing:.1em}.taqi-github-deployment>.gdp-box:first-of-type>h2:before{content:'REPOSITORY CONFIGURATION';display:block;margin-bottom:6px;color:#646970;font-size:10px;font-weight:700;letter-spacing:.1em}.taqi-github-deployment .gdp-hero-mark{font-size:0}.taqi-github-deployment .gdp-hero-mark:after{content:'\\2197';font-size:25px}.taqi-github-deployment .gdp-workflow:before{content:'1  Connect   →   2  Check   →   3  Review   →   4  Deploy';display:block;padding:11px 16px;background:#f6f8fb;color:#646970;font-size:11px;font-weight:700;letter-spacing:.02em;border-bottom:1px solid #e2e4e7}#wpfooter{position:static!important;clear:both}
        </style>
        <header class="gdp-hero"><div class="gdp-hero-main"><div class="gdp-hero-mark" aria-hidden="true">↗</div><div><div class="gdp-eyebrow">System / Release Control</div><h1>GitHub Deployment</h1><p>Review repository changes safely before they reach this application.</p></div></div><div class="gdp-hero-status"><span class="gdp-hero-status-label <?php echo 'Up to date' === $status['status'] ? 'is-good' : ( in_array( $status['status'], $warning_statuses, true ) ? 'is-error' : '' ); ?>"><?php echo esc_html( $status['status'] ); ?></span></div></header>
        <h1>GitHub Deployment</h1><p>Manage this application’s GitHub connection and review updates before deployment.</p>
        <?php if ( $message ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div><?php endif; ?><?php if ( $error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php endif; ?>
        <div class="gdp-box"><h2 style="margin-top:0">GitHub Repository</h2><p class="gdp-muted">This plugin checks the repository and reviews commits. It never pushes local changes.</p><form method="post"><input type="hidden" name="github_deployment_action" value="save"><?php wp_nonce_field( 'github_deployment_action', 'github_deployment_nonce' ); ?><div class="gdp-grid"><div class="gdp-field"><label><input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'], 'yes' ); ?>> Enable GitHub Deployment</label></div><div class="gdp-field"><label>Branch<input name="branch" value="<?php echo esc_attr( $settings['branch'] ); ?>" required></label></div><div class="gdp-field"><label>Remote name<input name="remote_name" value="<?php echo esc_attr( $settings['remote_name'] ); ?>" required></label></div><div class="gdp-field gdp-wide"><label>Repository URL<input type="url" name="repository_url" value="<?php echo esc_attr( $settings['repository_url'] ); ?>" required></label></div></div><div class="gdp-actions"><button class="button button-primary">Save Repository Settings</button><a class="button" href="<?php echo esc_url( $settings['repository_url'] ); ?>" target="_blank" rel="noopener noreferrer">Open GitHub Repository</a></div></form></div>
        <div class="gdp-box"><h2 style="margin-top:0">Safe Deployment Workflow</h2><p class="gdp-muted">Follow the numbered steps. Checking fetches remote metadata; application files are not changed by this page.</p><div class="gdp-status <?php echo 'Up to date' === $status['status'] ? 'gdp-good' : ( in_array( $status['status'], $warning_statuses, true ) ? 'gdp-warn' : '' ); ?>"><strong><?php echo esc_html( $status['status'] ); ?></strong><p><?php echo esc_html( $status['message'] ); ?></p><?php if ( $status['local'] || $status['remote'] ) : ?><p class="gdp-muted">Local: <code><?php echo esc_html( substr( $status['local'], 0, 12 ) ); ?></code> · GitHub: <code><?php echo esc_html( substr( $status['remote'], 0, 12 ) ); ?></code> · Branch: <?php echo esc_html( $status['branch'] ); ?></p><?php endif; ?></div><div class="gdp-workflow"><section class="gdp-step"><div class="gdp-number">1</div><div><h3>Verify repository connection</h3><p class="gdp-muted">Confirm the configured repository matches the local checkout.</p></div></section><section class="gdp-step"><div class="gdp-number">2</div><div><h3>Check GitHub for updates</h3><p class="gdp-muted">Fetch the configured branch and compare it with this local copy.</p><div class="gdp-actions"><form method="post"><input type="hidden" name="github_deployment_action" value="check"><?php wp_nonce_field( 'github_deployment_action', 'github_deployment_nonce' ); ?><button class="button button-primary">Check for Updates</button></form></div></div></section><section class="gdp-step"><div class="gdp-number">3</div><div><h3>Review commits</h3><?php if ( ! empty( $status['commits'] ) ) : ?><table class="gdp-commits"><thead><tr><th>Commit</th><th>Subject</th><th>Author</th><th>Date</th></tr></thead><tbody><?php foreach ( $status['commits'] as $commit ) : ?><tr><td><code><?php echo esc_html( $commit['short'] ); ?></code></td><td><?php echo esc_html( $commit['subject'] ); ?></td><td><?php echo esc_html( $commit['author'] ); ?></td><td><?php echo esc_html( $commit['date'] ); ?></td></tr><?php endforeach; ?></tbody></table><?php else : ?><p class="gdp-muted">No new commits are waiting for review.</p><?php endif; ?></div></section><section class="gdp-step"><div class="gdp-number">4</div><div><h3>Deploy deliberately</h3><p class="gdp-muted">Review commits and back up the site before pulling an approved update. Automatic deployment is not enabled by this plugin.</p></div></section></div><div class="gdp-note"><strong>Safety:</strong> local tracked changes, a wrong branch, or diverged history are shown as blockers. Untracked uploads and runtime files are not removed.</div></div></div></div>
        <?php
    }
}

new TAQI_GitHub_Deployment();
