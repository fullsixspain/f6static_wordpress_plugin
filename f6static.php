<?php
/*
 * Plugin Name: FullSIX Static Site Generation
 * Version: 1.0
 * Plugin URI: http://www.fullsix.es/
 * Description: Generate static site from WordPress site
 * Author: FullSIX Spain
 * Author URI: http://www.fullsix.es/
 * Requires at least: 3.0
 * Tested up to: 3.7.1
 * 
 * @package WordPress
 * @author Etienne Bernard
 * @since 1.0.0
 */

$uploadDir = wp_upload_dir();

$f6static_baseUrl = get_option('f6static_baseUrl', home_url());
$f6static_basePath = get_option('f6static_basePath', $uploadDir['basedir'] .'/f6static/');
$f6static_defaultFilename = get_option('f6static_defaultFilename', 'index.html');
$f6static_dynamic = get_option('f6static_dynamic', false);
$f6static_dynamicToken = get_option('f6static_dynamicToken', md5(rand()));
$f6static_runScript = get_option('f6static_runScript', false);

function f6static_render_tools_page()
{
    global $f6static_baseUrl, $f6static_basePath, $f6static_defaultFilename, $f6static_dynamic, $f6static_dynamicToken, $f6static_runScript;
    do_action('f6static-saveOptions');
?>
    <div class="wrap">
        <h2>F6 Static Site Generation</h2>

        <div class="postbox-container">
            <div class="metabox-holder">
                <div class="meta-box-sortables ui-sortable">
                    <form id="main-options" method="post">
                        <div class="postbox">
                            <h3>Options</h3>
                            <div class="inside">
                                <p>
                                    <label for="baseUrl">Static site base URL:</label>
                                    <input type="text" id="baseUrl" name="baseUrl" value="<?php echo esc_attr($f6static_baseUrl) ?>" size="50" />
                                </p>
                                <p>
                                    <label for="basePath">Static site base path:</label>
                                    <input type="text" id="basePath" name="basePath" value="<?php echo esc_attr($f6static_basePath) ?>" size="50" />
                                </p>
                                <p>
                                    <label for="defaultFilename">Default file name:</label>
                                    <input type="text" id="defaultFilename" name="defaultFilename" value="<?php echo esc_attr($f6static_defaultFilename) ?>" size="50" />
                                </p>
                                <p>
                                    <label for="defaultFilename">Generation for dynamic site?</label>
                                    <input type="checkbox" id="dynamic" name="dynamic" <?php if ($f6static_dynamic) { echo 'checked="checked"'; } ?>/>
                                </p>
                                <p>
                                    <label for="defaultFilename">Token for dynamic generation:</label>
                                    <input type="text" id="dynamicToken" name="dynamicToken" value="<?php echo esc_attr($f6static_dynamicToken) ?>" size="50" />
                                </p>
<!--
                                <p>
                                    <label for="runScript">Run script after generation:</label>
                                    <input type="checkbox" id="runScript" name="runScript" <?php if ($f6static_runScript) { echo 'checked="checked"'; } ?>/>
                                </p>
-->
                            </div>
                        </div>
                        <p class="submit">
                            <?php wp_nonce_field('f6static-options') ?>
                            <input class="button" type="submit" name="save" value="Save" />
                            <input class="button-primary" type="submit" name="generate" value="Save and Generate" />
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php
}

function f6static_save_options()
{
    global $f6static_baseUrl, $f6static_basePath, $f6static_defaultFilename, $f6static_dynamic, $f6static_dynamicToken, $f6static_runScript;
    if ($_SERVER['REQUEST_METHOD'] != 'POST') return;
    if (!check_admin_referer('f6static-options') || !current_user_can('manage_options')) {
        exit('Security risk');
    }
    /* Save data */
    $f6static_baseUrl = $_POST['baseUrl'];
    $f6static_basePath = $_POST['basePath'];
    $f6static_defaultFilename = $_POST['defaultFilename'];
    $f6static_dynamic = isset($_POST['dynamic']);
    $f6static_dynamicToken = $_POST['dynamicToken'];
    $f6static_runScript = isset($_POST['runScript']);
    update_option('f6static_baseUrl', $f6static_baseUrl);
    update_option('f6static_basePath', $f6static_basePath);
    update_option('f6static_defaultFilename', $f6static_defaultFilename);
    update_option('f6static_dynamic', $f6static_dynamic);
    update_option('f6static_dynamicToken', $f6static_dynamicToken);
    update_option('f6static_runScript', $f6static_runScript);
    /* Generate site */
    if (isset($_POST['generate'])) {
        f6static_generate();
    }
}

function f6static_register_settings()
{
    add_submenu_page('tools.php', 'F6 Static Site Generation', 'F6 Static Site Generation', 'manage_options', 'f6static-options', 'f6static_render_tools_page');
    add_action('f6static-saveOptions', 'f6static_save_options');
}

function f6static_generate()
{
    global $f6static_baseUrl, $f6static_basePath, $f6static_defaultFilename, $f6static_dynamic, $f6static_dynamicToken, $f6static_runScript;
    $basePath = trailingslashit($f6static_basePath);
    $baseUrl = untrailingslashit(home_url());
    $baseUrlSlash = trailingslashit($baseUrl);
    $newBaseUrl = untrailingslashit($f6static_baseUrl);
    $remainingUrls = array_unique(array_merge(
            array($baseUrlSlash),
            array() // TODO: Add theme files?
        ));
    $doneUrls = array();
    while (count($remainingUrls)) {
        /* Get url */
        $url = array_shift($remainingUrls);
        /* Already downloaded? */
        if (in_array($url, $doneUrls)) continue;
        /* Fetch page */
        $content = f6static_fetchUrl($url);
        if ($content !== null) {
            /* Get page links */
            $links = f6static_extractLinks($content, $baseUrl);
            /* Removed visited links */
            $newLinks = array_diff($links, $doneUrls, array($url));
            $remainingUrls = array_unique(array_merge($remainingUrls, $newLinks));
            /* Replace urls */
            $content = $content['body'];
            if (count($links) > 0) {
                $content = f6static_changeLinks($content, $baseUrl, $newBaseUrl);
            }
            /* Save page */
            f6static_savePage($url, $baseUrlSlash, $basePath, $f6static_defaultFilename, $content);
        }
        /* Mark as done */
        echo '<p>'.$url.'</p>';
        $doneUrls[] = $url;
    }
}

function f6static_fetchUrl($url) {
    global $f6static_dynamic, $f6static_dynamicToken;
    $url = filter_var($url, FILTER_VALIDATE_URL);
    $args = array('timeout' => 60);
    if ($f6static_dynamic) {
        $args['headers'] = array('X-F6Static-Token' => $f6static_dynamicToken);
    }
    $response = wp_remote_get($url, $args);
    return is_wp_error($response) ? null : $response;
}

function f6static_extractLinks($content, $baseUrl) {
    $contentType = isset($content['headers']['content-type']) ? $content['headers']['content-type'] : null;
    if (strpos($contentType, 'text/html') === false) return array();
    if (preg_match_all('/'.str_replace('/', '\/', $baseUrl).'[^ \?#"\']+/i', $content['body'], $matches)) {
        return array_unique($matches[0]);
    }
    return array();
}

function f6static_changeLinks($html, $baseUrl, $newBaseUrl)
{
    return str_replace($baseUrl, $newBaseUrl, $html);
}

function f6static_savePage($url, $baseUrl, $basePath, $defaultFilename, $content)
{
    $dest = str_replace($baseUrl, $basePath, $url);
    if (substr($dest, -1) == '/') {
        $dest .= $defaultFilename;
    }
    $directory = dirname($dest);
    if (!file_exists($directory)) {
        wp_mkdir_p($directory);
    }
    file_put_contents($dest, $content);
}

function f6static_init()
{
    add_shortcode('php', 'f6static_shortcode_php');
}

function f6static_shortcode_php($atts, $content = null)
{
    global $f6static_dynamic, $f6static_dynamicToken;
    ob_start();
    // Check current mode
    if ($f6static_dynamic && isset($_SERVER['HTTP_X_F6STATIC_TOKEN']) && $_SERVER['HTTP_X_F6STATIC_TOKEN'] == $f6static_dynamicToken) {
        echo '<?php '.$content.' ?>';
    } else {
        eval($content);
    }
    return ob_get_clean();
}

add_action('admin_menu', 'f6static_register_settings');
add_action('init', 'f6static_init');