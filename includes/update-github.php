<?php
/**
 * GitHub Auto-Update for Time Tracking Plugin
 * 
 * Checks for updates directly from GitHub releases
 * and provides update notifications in WordPress admin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Configuration
$github_repo_owner = 'sinanisler';
$github_repo_name  = 'time-tracking';
$plugin_slug       = 'time-tracking';
$plugin_file       = plugin_basename(plugin_dir_path(__DIR__) . 'time-tracking.php');

/**
 * Check for Plugin Updates from GitHub
 */
add_filter('pre_set_site_transient_update_plugins', 'time_tracking_check_github_update');
function time_tracking_check_github_update($transient) {
    global $github_repo_owner, $github_repo_name, $plugin_slug, $plugin_file;

    // Always initialize response and no_update arrays if not set
    if (!is_object($transient)) {
        $transient = new stdClass();
    }
    if (!isset($transient->response) || !is_array($transient->response)) {
        $transient->response = array();
    }
    if (!isset($transient->no_update) || !is_array($transient->no_update)) {
        $transient->no_update = array();
    }

    if (empty($transient->checked)) {
        return $transient;
    }

    // Get current plugin version
    if (!function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    $plugin_path = plugin_dir_path(__DIR__) . 'time-tracking.php';
    $plugin_data = get_plugin_data($plugin_path);
    $current_version = $plugin_data['Version'];

    // GitHub API URL for latest release
    $github_api_url = "https://api.github.com/repos/{$github_repo_owner}/{$github_repo_name}/releases/latest";

    // Fetch latest release from GitHub
    $response = wp_remote_get($github_api_url, array(
        'timeout' => 15,
        'headers' => array(
            'Accept'     => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
        ),
    ));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return $transient;
    }

    $release_data = json_decode(wp_remote_retrieve_body($response));

    if (!$release_data || !isset($release_data->tag_name)) {
        return $transient;
    }

    $latest_version = ltrim($release_data->tag_name, 'vV');
    $expected_asset_name = $plugin_slug . '.zip';
    $download_url = '';

    // Find the correct asset (plugin zip file)
    if (isset($release_data->assets) && is_array($release_data->assets)) {
        foreach ($release_data->assets as $asset) {
            if (isset($asset->browser_download_url) && $asset->name === $expected_asset_name) {
                $download_url = $asset->browser_download_url;
                break;
            }
        }
    }

    // If no specific asset found, use zipball_url as fallback
    if (empty($download_url) && isset($release_data->zipball_url)) {
        $download_url = $release_data->zipball_url;
    }

    // Only trigger update if new version exists and download URL is found
    if (
        $download_url
        && version_compare($latest_version, $current_version, '>')
    ) {
        $plugin_data = array(
            'id'          => "github.com/{$github_repo_owner}/{$github_repo_name}",
            'slug'        => $plugin_slug,
            'plugin'      => $plugin_file,
            'new_version' => $latest_version,
            'url'         => $release_data->html_url ?? '',
            'package'     => $download_url,
            'icons'       => array(),
            'banners'     => array(),
            'tested'      => get_bloginfo('version'),
            'requires_php'=> '8.0',
            'compatibility' => new stdClass(),
        );

        $transient->response[$plugin_file] = (object) $plugin_data;
    } else {
        // If no update is available, add to no_update to prevent WordPress from checking again
        if (isset($transient->no_update)) {
            $transient->no_update[$plugin_file] = (object) array(
                'id'          => "github.com/{$github_repo_owner}/{$github_repo_name}",
                'slug'        => $plugin_slug,
                'plugin'      => $plugin_file,
                'new_version' => $current_version,
                'url'         => "https://github.com/{$github_repo_owner}/{$github_repo_name}",
                'package'     => '',
                'icons'       => array(),
                'banners'     => array(),
                'tested'      => get_bloginfo('version'),
                'requires_php'=> '8.0',
                'compatibility' => new stdClass(),
            );
        }
    }

    return $transient;
}

/**
 * Provide Plugin Info for the "View version x.x.x details" popup
 */
add_filter('plugins_api', 'time_tracking_plugin_info_from_github', 10, 3);
function time_tracking_plugin_info_from_github($result, $action, $args) {
    global $github_repo_owner, $github_repo_name, $plugin_slug, $plugin_file;

    if ($action !== 'plugin_information' || $args->slug !== $plugin_slug) {
        return $result;
    }

    // GitHub API URL for latest release
    $github_api_url = "https://api.github.com/repos/{$github_repo_owner}/{$github_repo_name}/releases/latest";

    // Fetch latest release from GitHub
    $response = wp_remote_get($github_api_url, array(
        'timeout' => 15,
        'headers' => array(
            'Accept'     => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
        ),
    ));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return $result;
    }

    $release_data = json_decode(wp_remote_retrieve_body($response));
    if (!$release_data || !isset($release_data->tag_name)) {
        return $result;
    }

    $latest_version      = ltrim($release_data->tag_name, 'vV');
    $expected_asset_name = $plugin_slug . '.zip';
    $download_url        = '';

    // Find the correct asset
    if (isset($release_data->assets) && is_array($release_data->assets)) {
        foreach ($release_data->assets as $asset) {
            if (isset($asset->browser_download_url) && $asset->name === $expected_asset_name) {
                $download_url = $asset->browser_download_url;
                break;
            }
        }
    }

    // Fallback to zipball
    if (empty($download_url) && isset($release_data->zipball_url)) {
        $download_url = $release_data->zipball_url;
    }

    // Get current plugin data
    if (!function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    $plugin_path = plugin_dir_path(__DIR__) . 'time-tracking.php';
    $plugin_data = get_plugin_data($plugin_path);

    // Prepare changelog from release body
    $changelog = !empty($release_data->body) 
        ? wp_kses_post($release_data->body) 
        : __('See GitHub release notes for details.', 'time-tracking');

    // Prepare details for the WP plugin info popup
    $result = (object) array(
        'name'          => $plugin_data['Name'],
        'slug'          => $plugin_slug,
        'version'       => $latest_version,
        'author'        => $plugin_data['Author'],
        'homepage'      => $release_data->html_url ?? '',
        'requires'      => '5.8',
        'tested'        => get_bloginfo('version'),
        'requires_php'  => '8.0',
        'download_link' => $download_url,
        'sections'      => array(
            'description' => $plugin_data['Description'] ?? __('Advanced time tracking plugin with drag-to-select calendar, category management, and detailed time logging', 'time-tracking'),
            'changelog'   => '<h4>Version ' . esc_html($latest_version) . '</h4>' . $changelog,
        ),
        'banners'       => array(),
        'added'         => isset($release_data->published_at) ? date('Y-m-d', strtotime($release_data->published_at)) : '',
        'last_updated'  => isset($release_data->published_at) ? date('Y-m-d', strtotime($release_data->published_at)) : '',
    );

    return $result;
}

/**
 * Display custom update notification in plugins list
 */
add_action('after_plugin_row_' . $plugin_file, 'time_tracking_show_update_notification', 10, 2);
function time_tracking_show_update_notification($file, $plugin) {
    global $github_repo_owner, $github_repo_name, $plugin_file;

    if ($file !== $plugin_file) {
        return;
    }

    // Get update transient
    $update_cache = get_site_transient('update_plugins');

    if (!isset($update_cache->response[$plugin_file])) {
        return;
    }

    $update_data = $update_cache->response[$plugin_file];

    if (!isset($update_data->new_version)) {
        return;
    }

    // Get current version
    if (!function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $plugin_path = plugin_dir_path(__DIR__) . 'time-tracking.php';
    $plugin_data = get_plugin_data($plugin_path);
    $current_version = $plugin_data['Version'];

    // Only show if there's actually a new version
    if (version_compare($update_data->new_version, $current_version, '<=')) {
        return;
    }

    $wp_list_table = _get_list_table('WP_Plugins_List_Table');

    echo '<tr class="plugin-update-tr active" id="time-tracking-update" data-slug="time-tracking" data-plugin="' . esc_attr($plugin_file) . '">';
    echo '<td colspan="' . esc_attr($wp_list_table->get_column_count()) . '" class="plugin-update colspanchange">';
    echo '<div class="update-message notice inline notice-warning notice-alt">';
    echo '<p>';

    printf(
        __('There is a new version of %1$s available. <a href="%2$s" class="thickbox open-plugin-details-modal">View version %3$s details</a> or <a href="%4$s">update now</a>.', 'time-tracking'),
        esc_html($plugin_data['Name']),
        esc_url("https://github.com/{$github_repo_owner}/{$github_repo_name}/releases/tag/v{$update_data->new_version}"),
        esc_html($update_data->new_version),
        esc_url(wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=') . $plugin_file, 'upgrade-plugin_' . $plugin_file))
    );

    echo '</p>';
    echo '</div>';
    echo '</td>';
    echo '</tr>';
}

/**
 * Force check for updates when requested
 */
add_action('admin_init', 'time_tracking_force_update_check');
function time_tracking_force_update_check() {
    if (isset($_GET['force-check']) && $_GET['force-check'] === '1') {
        delete_site_transient('update_plugins');
        wp_redirect(remove_query_arg('force-check'));
        exit;
    }
}

/**
 * Add debug information in admin (only for administrators)
 */
add_action('admin_notices', 'time_tracking_update_debug_notice');
function time_tracking_update_debug_notice() {
    global $github_repo_owner, $github_repo_name, $plugin_file;

    // Only show on plugins page and only for admins with debug query param
    if (!current_user_can('manage_options') || !isset($_GET['tt-debug'])) {
        return;
    }

    $screen = get_current_screen();
    if ($screen->id !== 'plugins') {
        return;
    }

    // Get current plugin version
    if (!function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $plugin_path = plugin_dir_path(__DIR__) . 'time-tracking.php';
    $plugin_data = get_plugin_data($plugin_path);
    $current_version = $plugin_data['Version'];

    // Check update transient
    $update_cache = get_site_transient('update_plugins');
    $has_update = isset($update_cache->response[$plugin_file]);
    $update_info = $has_update ? $update_cache->response[$plugin_file] : null;

    // Fetch from GitHub
    $github_api_url = "https://api.github.com/repos/{$github_repo_owner}/{$github_repo_name}/releases/latest";
    $response = wp_remote_get($github_api_url, array('timeout' => 15));
    $github_version = 'N/A';

    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        $release_data = json_decode(wp_remote_retrieve_body($response));
        if ($release_data && isset($release_data->tag_name)) {
            $github_version = ltrim($release_data->tag_name, 'vV');
        }
    }

    echo '<div class="notice notice-info">';
    echo '<h3>Time Tracking Update Debug Info</h3>';
    echo '<ul>';
    echo '<li><strong>Plugin File:</strong> ' . esc_html($plugin_file) . '</li>';
    echo '<li><strong>Current Version:</strong> ' . esc_html($current_version) . '</li>';
    echo '<li><strong>GitHub Latest Version:</strong> ' . esc_html($github_version) . '</li>';
    echo '<li><strong>Update Available in Transient:</strong> ' . ($has_update ? 'YES' : 'NO') . '</li>';
    if ($has_update && $update_info) {
        echo '<li><strong>Update Version:</strong> ' . esc_html($update_info->new_version ?? 'N/A') . '</li>';
        echo '<li><strong>Package URL:</strong> ' . esc_html($update_info->package ?? 'N/A') . '</li>';
    }
    echo '<li><a href="' . esc_url(add_query_arg('force-check', '1')) . '" class="button">Force Update Check</a></li>';
    echo '</ul>';
    echo '</div>';
}

/**
 * Add JavaScript to admin footer to redirect version details link to GitHub
 */
add_action('admin_footer', 'time_tracking_github_redirect_version_link');
function time_tracking_github_redirect_version_link() {
    global $github_repo_owner, $github_repo_name;
    
    $github_url = "https://github.com/{$github_repo_owner}/{$github_repo_name}/releases";
    ?>
    <script type="text/javascript">
        (function() {
            const githubUrl = '<?php echo esc_js($github_url); ?>';
            const pluginSlug = 'time-tracking';
            
            function modifyLink(link) {
                const href = link.getAttribute('href');
                if (href && href.includes('plugin-install.php') && href.includes('tab=plugin-information') && href.includes(pluginSlug)) {
                    // Replace the href completely
                    link.href = githubUrl;
                    
                    // Remove thickbox classes
                    link.classList.remove('thickbox', 'open-plugin-details-modal');
                    
                    // Set target to open in new tab
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    
                    // Add click handler as extra safety
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        window.open(githubUrl, '_blank', 'noopener,noreferrer');
                        return false;
                    }, true);
                }
            }
            
            function processLinks() {
                // Target links in the plugins page
                const links = document.querySelectorAll('.plugin-title a, .update-message a, a[aria-label*="Time Tracking"]');
                links.forEach(modifyLink);
            }
            
            // Process on DOM ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', processLinks);
            } else {
                processLinks();
            }
            
            // Also watch for dynamically added links
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) {
                            if (node.matches && node.matches('a')) {
                                modifyLink(node);
                            }
                            const links = node.querySelectorAll && node.querySelectorAll('a');
                            if (links) {
                                links.forEach(modifyLink);
                            }
                        }
                    });
                });
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        })();
    </script>
    <?php
}
