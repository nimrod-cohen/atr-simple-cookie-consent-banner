<?php
/*
 * Plugin Name: ATR Simple Cookie Consent Banner for Israeli web sites
 * Description: Cookie consent banner specifically designed for Israeli websites to comply with the 13th amendment of the Privacy Protection Law (תיקון 13 לחוק הגנת הפרטיות). Handles Essential, Analytics, and Marketing cookies with proper consent management. Suitable for all Israeli businesses and websites. Use at your own risk - no warranty or liability for damages.
 * Plugin URI:        https://github.com/nimrod-cohen/atr-simple-cookie-consent-banner
 * Version:           1.0.6
 * Author:            nimrod-cohen
 * Author URI:        https://github.com/nimrod-cohen
 * Original Author:   Yehuda Tiram (https://atarimtr.co.il/)
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       atr-simple-cookie-consent-banner
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/github-updater.php';
add_action('init', function () {
  if (is_admin()) {
    new \ATRCookieConsent\GitHubPluginUpdater(__FILE__);
  }
});

function scb_default_banner_text() {
  return 'משתמשים בעוגיות כדי להבטיח תפקוד האתר ולשפר את חוויית המשתמש. אפשר לבחור אילו סוגי עוגיות להפעיל.';
}

function scb_get_banner_text() {
  $t = get_option('scb_banner_text', '');
  return $t !== '' ? $t : scb_default_banner_text();
}

function scb_get_colors() {
  return [
    'primary' => get_option('scb_color_primary', '#0b74de'),
    'bg' => get_option('scb_color_bg', '#ffffff'),
    'fg' => get_option('scb_color_fg', '#222222'),
  ];
}

function scb_color_inline_css() {
  $c = scb_get_colors();
  return sprintf(
    '#scb-banner{--scb-primary:%s;--scb-bg:%s;--scb-fg:%s;}',
    esc_attr($c['primary']),
    esc_attr($c['bg']),
    esc_attr($c['fg'])
  );
}

/* --- enqueue assets --- */
add_action('wp_enqueue_scripts', function () {
  $excluded_slugs = get_option('scb_excluded_pages', []);
  $excluded_slugs = apply_filters('scb_exclude_pages', $excluded_slugs);
  if (!empty($excluded_slugs) && is_page($excluded_slugs)) {
    return;
  }

  wp_register_style('scb-style', plugins_url('atr-scb.css', __FILE__), [], '1.0.3');
  wp_register_script('scb-script', plugins_url('atr-scb.js', __FILE__), [], '1.0.3', true);

  wp_enqueue_style('scb-style');
  wp_add_inline_style('scb-style', scb_color_inline_css());
  wp_enqueue_script('scb-script');

  // Check if we're on the privacy policy page
  $is_privacy_page = false;
  $privacy_policy_url = get_privacy_policy_url();
  if ($privacy_policy_url && (is_page() || is_single()) && get_permalink() === $privacy_policy_url) {
    $is_privacy_page = true;
  }

  // pass some settings to JS if needed
  wp_localize_script('scb-script', 'scbSettings', [
    'cookieName' => 'scb_consent',
    'expiryDays' => 365,
    'siteName' => get_bloginfo('name'),
    'isPrivacyPage' => $is_privacy_page,
    'privacyPolicyUrl' => $privacy_policy_url,
    'requireAnswer' => (bool) get_option('scb_require_answer', false)
  ]);
});

/* --- inject banner HTML in footer --- */
add_action('wp_footer', function () {
  $excluded_slugs = get_option('scb_excluded_pages', []);
  $excluded_slugs = apply_filters('scb_exclude_pages', $excluded_slugs);
  if (!empty($excluded_slugs) && is_page($excluded_slugs)) {
    return;
  }
  ?>
  <!-- Cookie Consent Banner (Injected by plugin) -->
  <div id="scb-overlay" aria-hidden="true"></div>

  <div id="scb-banner" role="dialog" aria-live="polite" aria-label="Cookie consent" aria-hidden="true">
    <div class="scb-content">
      <div class="scb-text">
        <strong><?php echo esc_html(get_bloginfo('name')); ?></strong>
        <?php echo esc_html(scb_get_banner_text()); ?>
      </div>

      <div class="scb-controls">
        <button id="scb-btn-accept-all" class="scb-btn scb-btn-primary" type="button">
          <span class="scb-btn-text">קבל הכל</span>
          <span class="scb-btn-loading" style="display: none;">טוען...</span>
        </button>
        <button id="scb-btn-reject" class="scb-btn" type="button">
          <span class="scb-btn-text">הסר לא הכרחיות</span>
          <span class="scb-btn-loading" style="display: none;">טוען...</span>
        </button>
        <button id="scb-btn-custom" class="scb-btn" type="button">העדפות</button>
      </div>

      <div id="scb-settings" class="scb-settings" hidden>
        <form id="scb-form">
          <fieldset>
            <legend>בחירת עוגיות</legend>
            <label><input type="checkbox" name="essential" checked disabled> הכרחיות (נדרשות)</label><br>
            <label><input type="checkbox" name="analytics"> אנליטיקה (Google Analytics)</label><br>
            <label><input type="checkbox" name="marketing"> שיווק/פרסום (Facebook/Ads)</label>
          </fieldset>

          <div class="scb-actions">
            <button type="submit" class="scb-btn scb-btn-primary">
              <span class="scb-btn-text">שמור בחירות</span>
              <span class="scb-btn-loading" style="display: none;">טוען...</span>
            </button>
            <button type="button" id="scb-btn-cancel" class="scb-btn">בטל</button>
          </div>
        </form>
      </div>
      <div class="scb-more" style="display: flex;justify-content: space-between;direction: ltr;"><a href="<?php echo esc_url(get_privacy_policy_url() ?: '#'); ?>" target="_blank" rel="noopener">מדיניות פרטיות</a>
      </div>
    </div>
  </div>
  <!-- End Cookie Consent Banner -->
<?php
});

/* --- optional: helper to print data-consent attributes for inline script placeholders --- */
/* Usage example in theme or plugin: <script type="text/plain" data-consent="analytics" src="..."></script>
The JS will replace it when consent for 'analytics' is given. */

// WooCommerce integration - only activate if WooCommerce is active
function scb_init_woocommerce_integration() {
  // Check if WooCommerce is active
  if (!class_exists('WooCommerce')) {
    return;
  }

  // הוספת צ'קבוקס אישור מדיניות פרטיות בעמוד התשלום
  add_action('woocommerce_review_order_before_submit', function () {
    woocommerce_form_field('privacy_policy_accepted', [
      'type' => 'checkbox',
      'class' => ['form-row privacy'],
      'label_class' => ['woocommerce-form__label woocommerce-form__label-for-checkbox checkbox'],
      'input_class' => ['woocommerce-form__input woocommerce-form__input-checkbox input-checkbox'],
      'required' => true,
      'label' => 'קראתי ואני מאשר/ת את <a href="' . esc_url(get_privacy_policy_url()) . '" target="_blank">מדיניות הפרטיות</a>'
    ]);
  }, 20);

  // ולידציה – לוודא שסומן
  add_action('woocommerce_checkout_process', function () {
    if (empty($_POST['privacy_policy_accepted'])) {
      wc_add_notice('יש לאשר את מדיניות הפרטיות לפני ביצוע ההזמנה.', 'error');
    }
  });

  // שמירת ההסכמה בהזמנה
  add_action('woocommerce_checkout_update_order_meta', function ($order_id) {
    if (!empty($_POST['privacy_policy_accepted'])) {
      update_post_meta($order_id, '_privacy_policy_accepted', 'yes');
    }
  });

  // הצגת ההסכמה בממשק הניהול
  add_action('woocommerce_admin_order_data_after_billing_address', function ($order) {
    $accepted = get_post_meta($order->get_id(), '_privacy_policy_accepted', true);
    if ($accepted === 'yes') {
      echo '<p><strong>אישור מדיניות פרטיות:</strong> כן</p>';
    }
  });
}

// Initialize WooCommerce integration
add_action('init', 'scb_init_woocommerce_integration');

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
  $links[] = '<a href="' . admin_url('options-general.php?page=scb-settings') . '">Settings</a>';
  return $links;
});

/* --- Admin settings page --- */
add_action('admin_menu', function () {
  add_options_page(
    'Cookie Consent Settings',
    'Cookie Consent',
    'manage_options',
    'scb-settings',
    'scb_render_settings_page'
  );
});

function scb_render_settings_page() {
  if (!current_user_can('manage_options')) return;

  if (isset($_POST['scb_save_excluded']) && check_admin_referer('scb_save_excluded')) {
    $selected = isset($_POST['scb_excluded_pages']) ? array_map('sanitize_text_field', $_POST['scb_excluded_pages']) : [];
    update_option('scb_excluded_pages', $selected);
    update_option('scb_require_answer', !empty($_POST['scb_require_answer']) ? 1 : 0);
    foreach (['primary', 'bg', 'fg'] as $k) {
      $v = isset($_POST['scb_color_' . $k]) ? sanitize_hex_color($_POST['scb_color_' . $k]) : null;
      if ($v) update_option('scb_color_' . $k, $v);
    }
    if (isset($_POST['scb_banner_text'])) {
      update_option('scb_banner_text', sanitize_textarea_field(wp_unslash($_POST['scb_banner_text'])));
    }
    echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
  }

  $excluded = get_option('scb_excluded_pages', []);
  $require_answer = (bool) get_option('scb_require_answer', false);
  $colors = scb_get_colors();
  $banner_text = scb_get_banner_text();
  $pages = get_pages(['sort_column' => 'post_title', 'sort_order' => 'ASC']);

  // Group pages by parent
  $top_level = [];
  $children = [];
  foreach ($pages as $page) {
    if ($page->post_parent == 0) {
      $top_level[] = $page;
    } else {
      $children[$page->post_parent][] = $page;
    }
  }
  ?>
  <style>
    .scb-header { display: flex; align-items: center; gap: 12px; margin-bottom: 4px; }
    .scb-header svg { width: 32px; height: 32px; }
    .scb-header h1 { margin: 0; padding: 0; font-size: 24px; font-weight: 600; }
    .scb-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 24px 28px; margin-top: 16px; }
    .scb-card h2 { margin: 0 0 4px; font-size: 16px; font-weight: 600; color: #1d2327; }
    .scb-card > p { color: #646970; font-size: 13px; margin: 0 0 16px; }
    .scb-search { width: 100%; padding: 10px 14px 10px 36px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box; background: #f9f9f9 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23999' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85zm-5.242.156a5 5 0 1 1 0-10 5 5 0 0 1 0 10z'/%3E%3C/svg%3E") 12px center no-repeat; transition: all 0.2s; }
    .scb-search:focus { border-color: #2271b1; box-shadow: 0 0 0 2px rgba(34,113,177,0.15); outline: none; background-color: #fff; }
    .scb-page-list { max-height: 420px; overflow-y: auto; margin-top: 12px; border: 1px solid #eee; border-radius: 6px; }
    .scb-page-item { display: flex; align-items: center; padding: 10px 14px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: background 0.15s; }
    .scb-page-item:last-child { border-bottom: none; }
    .scb-page-item:hover { background: #f7f9fc; }
    .scb-page-item.checked { background: #eef4fb; }
    .scb-page-item input[type=checkbox] { width: 18px; height: 18px; margin: 0; margin-inline-end: 10px; flex-shrink: 0; accent-color: #2271b1; cursor: pointer; }
    .scb-page-item .scb-title { flex: 1; font-size: 13.5px; color: #1d2327; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .scb-page-item .scb-slug { color: #a0a5aa; font-size: 12px; font-family: 'SF Mono', Consolas, monospace; direction: ltr; unicode-bidi: isolate; max-width: 50%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-inline-start: 8px; flex-shrink: 0; }
    .scb-page-item.child { padding-inline-start: 36px; }
    .scb-page-item.child .scb-title::before { content: '↳ '; color: #c3c4c7; }
    .scb-colors-layout { display: grid; grid-template-columns: max-content 1fr; gap: 24px; align-items: start; }
    .scb-color-grid { display: flex; flex-direction: column; gap: 12px; }
    .scb-color-grid label { display: flex; flex-direction: column; gap: 6px; font-size: 13px; color: #1d2327; }
    .scb-color-grid input[type=color] { width: 180px; height: 38px; border: 1px solid #ddd; border-radius: 6px; padding: 2px; cursor: pointer; background: #fff; }
    .scb-preview-stage { background: linear-gradient(135deg, #f3f4f6, #e5e7eb); border: 1px dashed #cbd0d4; border-radius: 8px; padding: 24px; min-height: 220px; display: flex; align-items: flex-end; justify-content: flex-end; }
    .scb-preview-banner { --scb-bg: <?= esc_attr($colors['bg']) ?>; --scb-fg: <?= esc_attr($colors['fg']) ?>; --scb-primary: <?= esc_attr($colors['primary']) ?>; --scb-primary-hover: color-mix(in srgb, var(--scb-primary) 80%, black); background: var(--scb-bg); color: var(--scb-fg); border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); padding: 16px; max-width: 360px; font-family: system-ui, -apple-system, sans-serif; }
    .scb-preview-banner .scb-pv-text { font-size: 13px; line-height: 1.4; margin-bottom: 12px; }
    .scb-preview-banner .scb-pv-text strong { display: block; margin-bottom: 4px; }
    .scb-preview-banner .scb-pv-controls { display: flex; gap: 8px; flex-wrap: wrap; }
    .scb-preview-banner .scb-pv-btn { padding: 8px 12px; border-radius: 6px; border: 1px solid #ccc; background: #f7f7f7; color: var(--scb-fg); font-size: 12px; cursor: default; }
    .scb-preview-banner .scb-pv-btn.primary { background: var(--scb-primary); color: #fff; border-color: transparent; }
    .scb-selected-tags { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; min-height: 32px; }
    .scb-tag { display: inline-flex; align-items: center; gap: 6px; background: linear-gradient(135deg, #2271b1, #135e96); color: #fff; border-radius: 16px; padding: 5px 14px; font-size: 12.5px; font-weight: 500; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
    .scb-tag .remove { cursor: pointer; opacity: 0.7; font-size: 15px; line-height: 1; transition: opacity 0.15s; }
    .scb-tag .remove:hover { opacity: 1; }
    .scb-no-tags { color: #a0a5aa; font-size: 13px; font-style: italic; padding: 4px 0; }
    .scb-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 14px; }
    .scb-count { color: #888; font-size: 13px; }
    .scb-submit { margin-top: 20px; }
    .scb-submit .button-primary { padding: 6px 24px; font-size: 14px; border-radius: 4px; }
  </style>
  <div class="wrap">
    <div class="scb-header">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="12" cy="12" r="10" fill="#f0d9a0" stroke="#c8a254" stroke-width="1.5"/>
        <circle cx="8" cy="9" r="1.5" fill="#8B6914"/>
        <circle cx="14" cy="7" r="1.2" fill="#8B6914"/>
        <circle cx="10" cy="14" r="1.4" fill="#8B6914"/>
        <circle cx="15.5" cy="13" r="1.1" fill="#8B6914"/>
        <circle cx="6.5" cy="13" r="0.9" fill="#8B6914"/>
        <circle cx="13" cy="17" r="1" fill="#8B6914"/>
        <path d="M5.5 7.5 Q7 6, 8.5 7" stroke="#c8a254" stroke-width="0.8" fill="none"/>
        <path d="M16 9 Q17.5 8, 18.5 9.5" stroke="#c8a254" stroke-width="0.8" fill="none"/>
      </svg>
      <h1>Cookie Consent Settings</h1>
    </div>
    <form method="post">
      <?php wp_nonce_field('scb_save_excluded'); ?>
      <div class="scb-card">
        <h2>Excluded Pages</h2>
        <p>The cookie consent banner will <strong>not</strong> appear on the selected pages.</p>

        <div class="scb-selected-tags" id="scb-tags">
          <?php if (empty($excluded)): ?>
            <span class="scb-no-tags">No pages excluded</span>
          <?php else: ?>
            <?php foreach ($excluded as $slug):
              $page_obj = get_page_by_path($slug);
              $title = $page_obj ? $page_obj->post_title : urldecode($slug);
            ?>
              <span class="scb-tag" data-slug="<?= esc_attr($slug) ?>">
                <?= esc_html($title) ?>
                <span class="remove" onclick="scbToggle('<?= esc_attr($slug) ?>', false)">&times;</span>
              </span>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <input type="text" class="scb-search" id="scb-search" placeholder="Filter pages..." autocomplete="off">

        <div class="scb-page-list" id="scb-list">
          <?php
          function scb_render_page_item($page, $excluded, $is_child = false) {
            $checked = in_array($page->post_name, $excluded);
            $class = 'scb-page-item' . ($is_child ? ' child' : '') . ($checked ? ' checked' : '');
            $decoded_slug = urldecode($page->post_name);
            ?>
            <label class="<?= $class ?>" data-title="<?= esc_attr(mb_strtolower($page->post_title)) ?>" data-slug="<?= esc_attr($page->post_name) ?>">
              <input type="checkbox" name="scb_excluded_pages[]" value="<?= esc_attr($page->post_name) ?>"
                <?php checked($checked); ?>
                onchange="scbUpdate(this)">
              <span class="scb-title"><?= esc_html($page->post_title) ?></span>
              <span class="scb-slug">/<?= esc_html($decoded_slug) ?>/</span>
            </label>
            <?php
          }
          foreach ($top_level as $page) {
            scb_render_page_item($page, $excluded);
            if (isset($children[$page->ID])) {
              foreach ($children[$page->ID] as $child) {
                scb_render_page_item($child, $excluded, true);
              }
            }
          }
          ?>
        </div>
        <div class="scb-footer">
          <span class="scb-count"><span id="scb-count"><?= count($excluded) ?></span> page(s) excluded</span>
        </div>
      </div>

      <div class="scb-card">
        <h2>Behavior</h2>
        <p>Control how visitors can dismiss the consent banner.</p>
        <label style="display:flex;align-items:center;gap:8px;font-size:13.5px;">
          <input type="checkbox" name="scb_require_answer" value="1" <?php checked($require_answer); ?>>
          <span>Require explicit answer (disable click-outside / Escape to close)</span>
        </label>
      </div>

      <div class="scb-card">
        <h2>Banner Text</h2>
        <p>The message shown to visitors. The site name above it stays as <code><?= esc_html(get_bloginfo('name')) ?></code>.</p>
        <textarea name="scb_banner_text" id="scb-banner-text" rows="3" style="width:100%;max-width:720px;font-size:14px;line-height:1.5;direction:rtl;padding:10px;border:1px solid #ddd;border-radius:6px;"><?= esc_textarea($banner_text) ?></textarea>
      </div>

      <div class="scb-card">
        <h2>Colors</h2>
        <p>Match the banner to your theme. Changes are previewed live below; save to apply.</p>
        <div class="scb-colors-layout">
          <div class="scb-color-grid">
            <label>Primary button
              <input type="color" id="scb-color-primary" name="scb_color_primary" value="<?= esc_attr($colors['primary']) ?>">
            </label>
            <label>Background
              <input type="color" id="scb-color-bg" name="scb_color_bg" value="<?= esc_attr($colors['bg']) ?>">
            </label>
            <label>Font color
              <input type="color" id="scb-color-fg" name="scb_color_fg" value="<?= esc_attr($colors['fg']) ?>">
            </label>
          </div>
          <div class="scb-preview-stage" dir="rtl">
          <div class="scb-preview-banner" id="scb-preview">
            <div class="scb-pv-text">
              <strong><?= esc_html(get_bloginfo('name')); ?></strong>
              <span id="scb-preview-text"><?= esc_html($banner_text) ?></span>
            </div>
            <div class="scb-pv-controls">
              <span class="scb-pv-btn primary">קבל הכל</span>
              <span class="scb-pv-btn">הסר לא הכרחיות</span>
              <span class="scb-pv-btn">העדפות</span>
            </div>
          </div>
          </div>
        </div>
      </div>

      <p class="scb-submit"><button type="submit" name="scb_save_excluded" class="button button-primary">Save Changes</button></p>
    </form>
  </div>
  <script>
  (function(){
    var search = document.getElementById('scb-search');
    var items = document.querySelectorAll('.scb-page-item');

    search.addEventListener('input', function() {
      var q = this.value.toLowerCase();
      items.forEach(function(item) {
        var match = item.dataset.title.includes(q) || item.dataset.slug.includes(q);
        item.style.display = match ? '' : 'none';
      });
    });
  })();

  function scbUpdate(cb) {
    var label = cb.closest('.scb-page-item');
    var tags = document.getElementById('scb-tags');
    var slug = cb.value;
    var title = label.querySelector('.scb-title').textContent;

    if (cb.checked) {
      label.classList.add('checked');
      var tag = document.createElement('span');
      tag.className = 'scb-tag';
      tag.dataset.slug = slug;
      tag.innerHTML = title + ' <span class="remove" onclick="scbToggle(\'' + slug + '\', false)">&times;</span>';
      tags.appendChild(tag);
    } else {
      label.classList.remove('checked');
      var tag = tags.querySelector('[data-slug="' + slug + '"]');
      if (tag) tag.remove();
    }
    document.getElementById('scb-count').textContent = document.querySelectorAll('.scb-page-item.checked').length;
  }

  function scbToggle(slug, state) {
    var item = document.querySelector('.scb-page-item[data-slug="' + slug + '"]');
    if (item) {
      var cb = item.querySelector('input');
      cb.checked = state;
      scbUpdate(cb);
    } else {
      var tag = document.querySelector('.scb-tag[data-slug="' + slug + '"]');
      if (tag) tag.remove();
      document.getElementById('scb-count').textContent = document.querySelectorAll('.scb-page-item.checked').length;
    }
  }

  (function(){
    var preview = document.getElementById('scb-preview');
    if (!preview) return;
    var bind = function(id, varName) {
      var el = document.getElementById(id);
      if (!el) return;
      el.addEventListener('input', function() {
        preview.style.setProperty(varName, el.value);
      });
    };
    bind('scb-color-primary', '--scb-primary');
    bind('scb-color-bg', '--scb-bg');
    bind('scb-color-fg', '--scb-fg');

    var ta = document.getElementById('scb-banner-text');
    var pvText = document.getElementById('scb-preview-text');
    if (ta && pvText) {
      ta.addEventListener('input', function() {
        pvText.textContent = ta.value;
      });
    }
  })();
  </script>
  <?php
}
