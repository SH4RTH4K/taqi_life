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
    private const REVIEW_OPTION   = 'taqi_github_deployment_review';
    private const AUDIT_OPTION    = 'taqi_github_deployment_audit';

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
                'visibility'     => 'public',
                'protocol'       => 'https',
                'repository_url' => 'https://github.com/SH4RTH4K/taqi_life.git',
                'ssh_repository_url' => 'git@github.com:SH4RTH4K/taqi_life.git',
                'ssh_key_path'   => '',
                'branch'         => 'main',
                'remote_name'    => 'origin',
            )
        );
    }

    private function git( $args, $allow_failure = true ) {
        if ( ! function_exists( 'exec' ) ) {
            return array( 'code' => 1, 'output' => 'PHP exec() is disabled on this server.' );
        }
        $settings = $this->settings();
        $command = 'git';
        if ( 'ssh' === $settings['protocol'] && $settings['ssh_key_path'] ) {
            $key_path = trim( (string) $settings['ssh_key_path'] );
            $ssh_command = 'ssh -i ' . escapeshellarg( $key_path ) . ' -o IdentitiesOnly=yes -o BatchMode=yes';
            $command = 'Windows' === PHP_OS_FAMILY ? 'set GIT_SSH_COMMAND=' . escapeshellarg( $ssh_command ) . '&& git' : 'GIT_SSH_COMMAND=' . escapeshellarg( $ssh_command ) . ' git';
        }
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

    private function valid_repository_url( $url, $protocol = 'https' ) {
        if ( 'ssh' === $protocol ) {
            return (bool) preg_match( '#^git@github\.com:[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+(?:\.git)?$#', trim( (string) $url ) );
        }
        return (bool) preg_match( '#^https://github\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+(?:\.git)?$#', trim( (string) $url ) );
    }

    private function status( $fetch = false ) {
        $settings = $this->settings();
        $base = array( 'status' => 'Not configured', 'message' => 'Enable GitHub Deployment and save the repository settings.', 'local' => '', 'remote' => '', 'branch' => $settings['branch'], 'ahead' => 0, 'behind' => 0, 'commits' => array(), 'changes' => array() );
        if ( 'yes' !== $settings['enabled'] ) {
            return $base;
        }
        $configured_url = 'ssh' === $settings['protocol'] ? $settings['ssh_repository_url'] : $settings['repository_url'];
        if ( ! $this->valid_repository_url( $configured_url, $settings['protocol'] ) ) {
            $base['status'] = 'Configuration error';
            $base['message'] = 'Enter a valid GitHub repository URL for the selected connection method.';
            return $base;
        }
        if ( 'ssh' === $settings['protocol'] ) {
            $key_path = trim( (string) $settings['ssh_key_path'] );
            if ( '' === $key_path ) {
                $base['status'] = 'SSH credential missing';
                $base['message'] = 'SSH is selected, but no private-key path is configured.';
                return $base;
            }
            if ( ! file_exists( $key_path ) || ! is_readable( $key_path ) ) {
                $base['status'] = 'SSH credential unavailable';
                $base['message'] = 'The configured SSH private key does not exist or is not readable by PHP.';
                return $base;
            }
        }
        $inside = $this->git( array( 'rev-parse', '--is-inside-work-tree' ) );
        if ( 0 !== $inside['code'] || 'true' !== trim( $inside['output'] ) ) {
            $base['status'] = 'Git unavailable';
            $base['message'] = 'This installation is not inside a Git working tree.';
            return $base;
        }
        $remote = $this->git( array( 'remote', 'get-url', $settings['remote_name'] ) );
        if ( 0 !== $remote['code'] || $this->normalize_url( $remote['output'] ) !== $this->normalize_url( $configured_url ) ) {
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
        $base['ahead']  = absint( $ahead );
        $base['behind'] = absint( $behind );
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

    private function review() {
        return wp_parse_args( get_option( self::REVIEW_OPTION, array() ), array( 'commit' => '', 'comment' => '', 'reviewer' => '', 'reviewed_at' => '', 'approved_commit' => '', 'approved_by' => '', 'approved_at' => '' ) );
    }

    private function audit() {
        $audit = get_option( self::AUDIT_OPTION, array() );
        return is_array( $audit ) ? array_slice( $audit, 0, 20 ) : array();
    }

    private function add_audit( $event, $message, $commit = '' ) {
        $audit = $this->audit();
        array_unshift( $audit, array( 'event' => sanitize_key( $event ), 'message' => sanitize_text_field( $message ), 'commit' => sanitize_text_field( $commit ), 'user' => wp_get_current_user()->display_name, 'at' => current_time( 'mysql' ) ) );
        update_option( self::AUDIT_OPTION, $audit, false );
    }

    private function deploy( $status, $review ) {
        if ( 'Update available' !== $status['status'] || empty( $status['remote'] ) ) {
            return new WP_Error( 'taqi_deploy_not_ready', 'Deployment is blocked: refresh status and confirm that an update is available.' );
        }
        if ( empty( $review['comment'] ) || $review['commit'] !== $status['remote'] || $review['approved_commit'] !== $status['remote'] ) {
            return new WP_Error( 'taqi_deploy_not_approved', 'Deployment is blocked until this exact commit has a review comment and approval.' );
        }

        $uploads = wp_upload_dir();
        $backup_dir = trailingslashit( $uploads['basedir'] ) . 'taqi-deployment-backups';
        wp_mkdir_p( $backup_dir );
        $backup = trailingslashit( $backup_dir ) . 'before-' . gmdate( 'Ymd-His' ) . '-' . substr( $status['local'], 0, 12 ) . '.zip';
        $archive = $this->git( array( 'archive', '--format=zip', '-o', $backup, 'HEAD' ) );
        if ( 0 !== $archive['code'] || ! file_exists( $backup ) ) {
            return new WP_Error( 'taqi_backup_failed', 'Deployment blocked because the pre-deployment code backup could not be created.' );
        }

        $pulled = $this->git( array( 'pull', '--ff-only', $this->settings()['remote_name'], $this->settings()['branch'] ) );
        if ( 0 !== $pulled['code'] ) {
            return new WP_Error( 'taqi_deploy_failed', 'Deployment failed after backup: ' . $pulled['output'] );
        }

        $review['approved_commit'] = '';
        update_option( self::REVIEW_OPTION, $review, false );
        $this->add_audit( 'deploy', 'Deployed approved commit ' . substr( $status['remote'], 0, 12 ) . '. Backup: ' . basename( $backup ), $status['remote'] );
        return array( 'message' => 'Deployment completed successfully. Backup created: ' . basename( $backup ) );
    }

    private function quick_sync( $status ) {
        if ( empty( $status['remote'] ) || in_array( $status['status'], array( 'Wrong local branch', 'Diverged branch', 'Repository mismatch', 'Configuration error', 'Git unavailable', 'Connection failed', 'Branch not found', 'SSH credential missing', 'SSH credential unavailable' ), true ) ) {
            return new WP_Error( 'taqi_sync_not_ready', 'Update cannot start until the repository and branch connection is valid.' );
        }

        $uploads = wp_upload_dir();
        $backup_dir = trailingslashit( $uploads['basedir'] ) . 'taqi-deployment-backups';
        wp_mkdir_p( $backup_dir );
        $backup = trailingslashit( $backup_dir ) . 'before-sync-' . gmdate( 'Ymd-His' ) . '-' . substr( $status['local'], 0, 12 ) . '.zip';
        $archive = $this->git( array( 'archive', '--format=zip', '-o', $backup, 'HEAD' ) );
        if ( 0 !== $archive['code'] || ! file_exists( $backup ) ) {
            return new WP_Error( 'taqi_backup_failed', 'Update blocked because the pre-update code backup could not be created.' );
        }

        $remote_ref = $this->settings()['remote_name'] . '/' . $this->settings()['branch'];
        $synced = $this->git( array( 'reset', '--hard', $remote_ref ) );
        if ( 0 !== $synced['code'] ) {
            return new WP_Error( 'taqi_sync_failed', 'cPanel update failed after backup: ' . $synced['output'] );
        }

        update_option( self::REVIEW_OPTION, array( 'commit' => '', 'comment' => '', 'reviewer' => '', 'reviewed_at' => '', 'approved_commit' => '', 'approved_by' => '', 'approved_at' => '' ), false );
        $this->add_audit( 'sync', 'Updated cPanel directly to GitHub commit ' . substr( $status['remote'], 0, 12 ) . '. Local tracked changes were replaced. Backup: ' . basename( $backup ), $status['remote'] );
        return array( 'message' => 'cPanel updated from GitHub. Backup created: ' . basename( $backup ) );
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
                $ssh_repository = sanitize_text_field( trim( wp_unslash( $_POST['ssh_repository_url'] ?? '' ) ) );
                $ssh_key_path = sanitize_text_field( trim( wp_unslash( $_POST['ssh_key_path'] ?? '' ) ) );
                $visibility = in_array( sanitize_key( wp_unslash( $_POST['visibility'] ?? 'public' ) ), array( 'public', 'private' ), true ) ? sanitize_key( wp_unslash( $_POST['visibility'] ?? 'public' ) ) : 'public';
                $protocol = in_array( sanitize_key( wp_unslash( $_POST['protocol'] ?? 'https' ) ), array( 'https', 'ssh' ), true ) ? sanitize_key( wp_unslash( $_POST['protocol'] ?? 'https' ) ) : 'https';
                $branch = sanitize_text_field( trim( wp_unslash( $_POST['branch'] ?? 'main' ) ) );
                $remote = sanitize_text_field( trim( wp_unslash( $_POST['remote_name'] ?? 'origin' ) ) );
                if ( ! $this->valid_repository_url( $repository, 'https' ) || ! $this->valid_repository_url( $ssh_repository, 'ssh' ) || ! preg_match( '/^[A-Za-z0-9._-]{1,100}$/', $branch ) || ! preg_match( '/^[A-Za-z0-9._-]{1,50}$/', $remote ) || ( 'ssh' === $protocol && '' === $ssh_key_path ) ) {
                    $error = 'Enter valid repository settings and an SSH private-key path when SSH is selected.';
                } else {
                    update_option( self::SETTINGS_OPTION, array( 'enabled' => ! empty( $_POST['enabled'] ) ? 'yes' : 'no', 'visibility' => $visibility, 'protocol' => $protocol, 'repository_url' => $repository, 'ssh_repository_url' => $ssh_repository, 'ssh_key_path' => $ssh_key_path, 'branch' => $branch, 'remote_name' => $remote ), false );
                    $settings = $this->settings();
                    $message = 'GitHub Deployment settings saved.';
                }
            } elseif ( 'check' === $action ) {
                $status = $this->status( true );
                update_option( self::STATUS_OPTION, $status, false );
                $message = 'GitHub status refreshed.';
                if ( in_array( $status['status'], array( 'Connection failed', 'Git unavailable', 'Repository mismatch', 'Configuration error', 'Branch not found', 'SSH credential missing', 'SSH credential unavailable' ), true ) ) {
                    $error = $status['message'];
                }
            } elseif ( 'review' === $action ) {
                $status = $this->status();
                $comment = sanitize_textarea_field( wp_unslash( $_POST['review_comment'] ?? '' ) );
                if ( 'Update available' !== $status['status'] || empty( $status['remote'] ) || '' === $comment ) {
                    $error = 'Add a review comment after checking GitHub for an available update.';
                } else {
                    update_option( self::REVIEW_OPTION, array( 'commit' => $status['remote'], 'comment' => $comment, 'reviewer' => wp_get_current_user()->display_name, 'reviewed_at' => current_time( 'mysql' ), 'approved_commit' => '', 'approved_by' => '', 'approved_at' => '' ), false );
                    $this->add_audit( 'review', 'Review comment added for commit ' . substr( $status['remote'], 0, 12 ), $status['remote'] );
                    $message = 'Review comment saved for this commit.';
                }
            } elseif ( 'approve' === $action ) {
                $status = $this->status();
                $review = $this->review();
                if ( 'Update available' !== $status['status'] || empty( $review['comment'] ) || $review['commit'] !== $status['remote'] ) {
                    $error = 'Approval is blocked until the current remote commit has a review comment.';
                } else {
                    $review['approved_commit'] = $status['remote'];
                    $review['approved_by'] = wp_get_current_user()->display_name;
                    $review['approved_at'] = current_time( 'mysql' );
                    update_option( self::REVIEW_OPTION, $review, false );
                    $this->add_audit( 'approve', 'Approved commit ' . substr( $status['remote'], 0, 12 ) . ' for deployment.', $status['remote'] );
                    $message = 'Commit approved. It is ready for deployment.';
                }
            } elseif ( 'deploy' === $action ) {
                $status = $this->status( true );
                $review = $this->review();
                $result = $this->deploy( $status, $review );
                if ( is_wp_error( $result ) ) {
                    $error = $result->get_error_message();
                } else {
                    $message = $result['message'];
                    update_option( self::STATUS_OPTION, $this->status(), false );
                }
            } elseif ( 'sync' === $action ) {
                $status = $this->status( true );
                $result = $this->quick_sync( $status );
                if ( is_wp_error( $result ) ) {
                    $error = $result->get_error_message();
                } else {
                    $message = $result['message'];
                    update_option( self::STATUS_OPTION, $this->status(), false );
                }
            }
        }
        $settings = $this->settings();
        $status = get_option( self::STATUS_OPTION, array() );
        if ( ! is_array( $status ) || empty( $status['status'] ) ) {
            $status = $this->status();
        }
        $warning_statuses = array( 'Local changes detected', 'Diverged branch', 'Wrong local branch', 'SSH credential missing', 'SSH credential unavailable', 'Connection failed' );
        $review = $this->review();
        $audit  = $this->audit();
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
        <div class="gdp-box"><h2 style="margin-top:0">Quick cPanel Update</h2><p class="gdp-muted">Use this when the desired commit is already on GitHub. It creates a code backup, then synchronizes tracked WordPress files directly from the configured branch.</p><form method="post" onsubmit="return confirm('Update cPanel from GitHub now? Tracked local code changes will be replaced. Untracked uploads and runtime files will be preserved.');"><input type="hidden" name="github_deployment_action" value="sync"><?php wp_nonce_field( 'github_deployment_action', 'github_deployment_nonce' ); ?><button class="button button-primary" <?php disabled( empty( $status['remote'] ) || in_array( $status['status'], array( 'Wrong local branch', 'Diverged branch', 'Repository mismatch', 'Configuration error', 'Git unavailable', 'Connection failed', 'Branch not found', 'SSH credential missing', 'SSH credential unavailable' ), true ) ); ?>>Update cPanel from GitHub</button><span class="gdp-muted" style="margin-left:10px">Review comment and approval are not required for this direct update.</span></form></div>
        <div class="gdp-box"><h2 style="margin-top:0">Release Review and Deployment</h2><p class="gdp-muted">A deployment requires a review comment and approval for the exact commit currently on GitHub.</p><?php if ( $review['commit'] ) : ?><div class="gdp-status <?php echo $review['approved_commit'] === $review['commit'] ? 'gdp-good' : 'gdp-warn'; ?>"><strong><?php echo $review['approved_commit'] === $review['commit'] ? 'Approved for deployment' : 'Review recorded'; ?></strong><p><code><?php echo esc_html( substr( $review['commit'], 0, 12 ) ); ?></code> · <?php echo esc_html( $review['reviewer'] ); ?> · <?php echo esc_html( $review['reviewed_at'] ); ?></p><p><?php echo esc_html( $review['comment'] ); ?></p></div><?php endif; ?><form method="post"><input type="hidden" name="github_deployment_action" value="review"><?php wp_nonce_field( 'github_deployment_action', 'github_deployment_nonce' ); ?><label><strong>Review comment</strong><textarea name="review_comment" rows="3" style="width:100%;margin-top:6px" required><?php echo esc_textarea( $review['comment'] ); ?></textarea></label><div class="gdp-actions"><button class="button button-primary">Save Review Comment</button></div></form><div class="gdp-actions"><form method="post"><input type="hidden" name="github_deployment_action" value="approve"><?php wp_nonce_field( 'github_deployment_action', 'github_deployment_nonce' ); ?><button class="button" <?php disabled( empty( $review['comment'] ) || 'Update available' !== $status['status'] || $review['commit'] !== $status['remote'] ); ?>>Approve This Commit</button></form><form method="post"><input type="hidden" name="github_deployment_action" value="deploy"><?php wp_nonce_field( 'github_deployment_action', 'github_deployment_nonce' ); ?><button class="button button-primary" <?php disabled( $review['approved_commit'] !== $status['remote'] || 'Update available' !== $status['status'] ); ?>>Deploy Approved Commit</button></form></div></div>
        <?php if ( $audit ) : ?><div class="gdp-box"><h2 style="margin-top:0">Deployment Audit Trail</h2><table class="gdp-commits"><thead><tr><th>Event</th><th>Details</th><th>User</th><th>Date</th></tr></thead><tbody><?php foreach ( $audit as $entry ) : ?><tr><td><?php echo esc_html( ucfirst( $entry['event'] ) ); ?></td><td><?php echo esc_html( $entry['message'] ); ?><?php if ( ! empty( $entry['commit'] ) ) : ?> <code><?php echo esc_html( substr( $entry['commit'], 0, 12 ) ); ?></code><?php endif; ?></td><td><?php echo esc_html( $entry['user'] ); ?></td><td><?php echo esc_html( $entry['at'] ); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        <div class="gdp-box"><h2 style="margin-top:0">GitHub Repository</h2><p class="gdp-muted">Configure public/private access and the connection method used by this WordPress server.</p><form method="post"><input type="hidden" name="github_deployment_action" value="save"><?php wp_nonce_field( 'github_deployment_action', 'github_deployment_nonce' ); ?><div class="gdp-grid"><div class="gdp-field"><label><input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'], 'yes' ); ?>> Enable GitHub Deployment</label></div><div class="gdp-field"><label>Repository visibility<select name="visibility" style="width:100%;margin-top:6px;min-height:36px"><option value="public" <?php selected( $settings['visibility'], 'public' ); ?>>Public</option><option value="private" <?php selected( $settings['visibility'], 'private' ); ?>>Private</option></select></label></div><div class="gdp-field"><label>Connection method<select name="protocol" style="width:100%;margin-top:6px;min-height:36px"><option value="https" <?php selected( $settings['protocol'], 'https' ); ?>>HTTPS</option><option value="ssh" <?php selected( $settings['protocol'], 'ssh' ); ?>>SSH</option></select></label></div><div class="gdp-field"><label>Branch<input name="branch" value="<?php echo esc_attr( $settings['branch'] ); ?>" required></label></div><div class="gdp-field"><label>Remote name<input name="remote_name" value="<?php echo esc_attr( $settings['remote_name'] ); ?>" required></label></div><div class="gdp-field gdp-wide"><label>HTTPS repository URL<input type="url" name="repository_url" value="<?php echo esc_attr( $settings['repository_url'] ); ?>" required></label></div><div class="gdp-field gdp-wide"><label>SSH repository URL<input type="text" name="ssh_repository_url" value="<?php echo esc_attr( $settings['ssh_repository_url'] ); ?>" required><span class="gdp-muted">Example: git@github.com:owner/repository.git.</span></label></div><div class="gdp-field gdp-wide"><label>SSH private-key path<input type="text" name="ssh_key_path" value="<?php echo esc_attr( $settings['ssh_key_path'] ); ?>" placeholder="/home/account/.ssh/github_deploy_key"><span class="gdp-muted">Required when SSH is selected. Enter the server path only; never paste the private key into WordPress. The key must be readable by the PHP/web-server user.</span></label></div></div><div class="gdp-actions"><button class="button button-primary">Save Repository Settings</button><a class="button" href="<?php echo esc_url( $settings['repository_url'] ); ?>" target="_blank" rel="noopener noreferrer">Open GitHub Repository</a></div></form></div>
        <div class="gdp-box"><h2 style="margin-top:0">Safe Deployment Workflow</h2><p class="gdp-muted">Follow the numbered steps. Checking fetches remote metadata; application files are not changed by this page.</p><div class="gdp-status <?php echo 'Up to date' === $status['status'] ? 'gdp-good' : ( in_array( $status['status'], $warning_statuses, true ) ? 'gdp-warn' : '' ); ?>"><strong><?php echo esc_html( $status['status'] ); ?></strong><p><?php echo esc_html( $status['message'] ); ?></p><?php if ( $status['local'] || $status['remote'] ) : ?><p class="gdp-muted">Local: <code><?php echo esc_html( substr( $status['local'], 0, 12 ) ); ?></code> · GitHub: <code><?php echo esc_html( substr( $status['remote'], 0, 12 ) ); ?></code> · Branch: <?php echo esc_html( $status['branch'] ); ?></p><?php endif; ?></div><div class="gdp-workflow"><section class="gdp-step"><div class="gdp-number">1</div><div><h3>Verify repository connection</h3><p class="gdp-muted">Confirm the configured repository matches the local checkout.</p></div></section><section class="gdp-step"><div class="gdp-number">2</div><div><h3>Check GitHub for updates</h3><p class="gdp-muted">Fetch the configured branch and compare it with this local copy.</p><div class="gdp-actions"><form method="post"><input type="hidden" name="github_deployment_action" value="check"><?php wp_nonce_field( 'github_deployment_action', 'github_deployment_nonce' ); ?><button class="button button-primary">Check for Updates</button></form></div></div></section><section class="gdp-step"><div class="gdp-number">3</div><div><h3>Review commits</h3><?php if ( ! empty( $status['commits'] ) ) : ?><table class="gdp-commits"><thead><tr><th>Commit</th><th>Subject</th><th>Author</th><th>Date</th></tr></thead><tbody><?php foreach ( $status['commits'] as $commit ) : ?><tr><td><code><?php echo esc_html( $commit['short'] ); ?></code></td><td><?php echo esc_html( $commit['subject'] ); ?></td><td><?php echo esc_html( $commit['author'] ); ?></td><td><?php echo esc_html( $commit['date'] ); ?></td></tr><?php endforeach; ?></tbody></table><?php else : ?><p class="gdp-muted">No new commits are waiting for review.</p><?php endif; ?></div></section><section class="gdp-step"><div class="gdp-number">4</div><div><h3>Deploy deliberately</h3><p class="gdp-muted">Review commits and back up the site before pulling an approved update. Automatic deployment is not enabled by this plugin.</p></div></section></div><div class="gdp-note"><strong>Safety:</strong> local tracked changes, a wrong branch, or diverged history are shown as blockers. Untracked uploads and runtime files are not removed.</div></div></div></div>
        <?php
    }
}

new TAQI_GitHub_Deployment();
