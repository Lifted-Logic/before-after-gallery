<?php
/**
 * Admin view: How To page
 */

defined('ABSPATH') || exit;
?>
<div class="wrap">
  <h1><?php esc_html_e('How To', 'll-bag'); ?></h1>
  <div class="ll-bag-how-to">

    <nav class="ll-bag-how-to__nav" aria-label="<?php esc_attr_e('Page sections', 'll-bag'); ?>">
      <ul class="ll-bag-how-to__nav-list">
        <li>
          <a href="#quick-start" class="ll-bag-how-to__nav-link">
            <?php esc_html_e('Quick Start', 'll-bag'); ?>
          </a>
        </li>
        <li>
          <a href="#setting-up-filters" class="ll-bag-how-to__nav-link">
            <?php esc_html_e('Setting up Filters', 'll-bag'); ?>
          </a>
        </li>
        <li>
          <a href="#adding-posts" class="ll-bag-how-to__nav-link">
            <?php esc_html_e('Adding Posts', 'll-bag'); ?>
          </a>
        </li>
        <li>
          <a href="#archive-page" class="ll-bag-how-to__nav-link">
            <?php esc_html_e('Archive Page', 'll-bag'); ?>
          </a>
        </li>
        <li>
          <a href="#category-page" class="ll-bag-how-to__nav-link">
            <?php esc_html_e('Category Page', 'll-bag'); ?>
          </a>
        </li>
      </ul>
    </nav>

    <div class="ll-bag-how-to__content">

      <section id="quick-start" class="ll-bag-how-to__section">
        <h2><?php esc_html_e('Quick Start', 'll-bag'); ?></h2>
        <p><?php esc_html_e('New to the plugin? Follow these steps to get your gallery up and running. Each step links to the detailed instructions further down this page.', 'll-bag'); ?></p>
        <ol class="ll-bag-how-to__steps">
          <li>
            <strong><a href="#archive-page"><?php esc_html_e('Create your gallery page.', 'll-bag'); ?></a></strong>
            <?php esc_html_e('Make a new WordPress page, then assign it under Before & Afters → Settings → Archive Settings. Save your permalinks afterward (Settings → Permalinks → Save Changes).', 'll-bag'); ?>
          </li>
          <li>
            <strong><a href="#setting-up-filters"><?php esc_html_e('Set up your filters.', 'll-bag'); ?></a></strong>
            <?php esc_html_e('Go to Before & Afters → Filter Settings and add the filters visitors will use to browse the gallery — things like treatment type, body area, or provider.', 'll-bag'); ?>
          </li>
          <li>
            <strong><a href="#adding-posts"><?php esc_html_e('Add your first post.', 'll-bag'); ?></a></strong>
            <?php esc_html_e('Go to Before & Afters → Add New Before & After. Upload your images, fill in the details, and assign the post to the filters you just created.', 'll-bag'); ?>
          </li>
          <li>
            <strong><?php esc_html_e('Visit your gallery page.', 'll-bag'); ?></strong>
            <?php esc_html_e('Your post will now appear in the gallery. From here, keep adding posts and use the sections below any time you need a refresher.', 'll-bag'); ?>
          </li>
        </ol>
      </section>

      <section id="setting-up-filters" class="ll-bag-how-to__section">
        <h2><?php esc_html_e('Setting up Filters', 'll-bag'); ?></h2>

        <p><?php esc_html_e('Filters let visitors narrow down the gallery by things like treatment type, body area, or provider. You control which filters appear and how they behave from the Filter Settings page.', 'll-bag'); ?></p>

        <img
          src="<?= esc_url(LL_BAG_URL . 'resources/img/before-after__filter-settings.png') ?>"
          alt="<?php esc_attr_e('Screenshot of the Filter Settings page', 'll-bag'); ?>"
          class="ll-bag-how-to__screenshot"
        >

        <h3><?php esc_html_e('What each column means', 'll-bag'); ?></h3>
        <dl class="ll-bag-how-to__def-list">
          <dt><?php esc_html_e('Label', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('The name of the filter as visitors will see it in the sidebar. For custom filters you can edit this directly — just click into the text field and type your name.', 'll-bag'); ?></dd>

          <dt><?php esc_html_e('Filterable?', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('When checked, this filter shows up in the gallery sidebar so visitors can use it to narrow results. Uncheck it to hide the filter without deleting it.', 'll-bag'); ?></dd>

          <dt><?php esc_html_e('Show Search Bar?', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('Adds a small search box inside the filter so visitors can type to find a specific term rather than scrolling through a long list. This option is only available when Filterable is checked.', 'll-bag'); ?></dd>

          <dt><?php esc_html_e('Show on Cards', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('When checked, this filter\'s tags will appear as small labels on each gallery card. Only one filter can have this enabled at a time — selecting a new one will automatically uncheck the previous one.', 'll-bag'); ?></dd>
        </dl>

        <h3><?php esc_html_e('Adding a new filter', 'll-bag'); ?></h3>
        <ol class="ll-bag-how-to__steps">
          <li><?php esc_html_e('Click the "+ Add Filter" button at the top of the page.', 'll-bag'); ?></li>
          <li><?php esc_html_e('A new row will appear at the bottom of the list. Type a name for your filter in the Label field.', 'll-bag'); ?></li>
          <li><?php esc_html_e('Check the options you want for this filter using the checkboxes.', 'll-bag'); ?></li>
          <li><?php esc_html_e('Click "Save Filters" when you\'re done. Your changes won\'t be applied until you save.', 'll-bag'); ?></li>
        </ol>

        <h3><?php esc_html_e('Reordering filters', 'll-bag'); ?></h3>
        <p><?php esc_html_e('Grab the dotted handle on the far left of any row and drag it up or down to change the order filters appear in the sidebar. Click "Save Filters" to keep the new order.', 'll-bag'); ?></p>

        <h3><?php esc_html_e('Removing a filter', 'll-bag'); ?></h3>
        <p><?php esc_html_e('Click the "Remove" button on the right side of the row, then click "Save Filters". Note that built-in filters like Categories and Tags do not have a Remove button — these are managed through WordPress directly.', 'll-bag'); ?></p>
      </section>

      <section id="adding-posts" class="ll-bag-how-to__section">
        <h2><?php esc_html_e('Adding Posts', 'll-bag'); ?></h2>

        <p><?php esc_html_e('Each post in the gallery represents one before & after case. Posts can include one or more images or videos, supporting details, and tags that help visitors filter and find relevant content.', 'll-bag'); ?></p>

        <ol class="ll-bag-how-to__steps">
          <li><?php esc_html_e('In the left menu, go to Before & Afters and click "Add New Before & After".', 'll-bag'); ?></li>
          <li><?php esc_html_e('Enter a title at the top of the page. This is used internally to identify the post and may appear in search results.', 'll-bag'); ?></li>
          <li><?php esc_html_e('Fill in the fields on the left-side tabs (Details, Images, Settings), then publish when ready.', 'll-bag'); ?></li>
        </ol>

        <h3><?php esc_html_e('Details Tab', 'll-bag'); ?></h3>
        <dl class="ll-bag-how-to__def-list">
          <dt><?php esc_html_e('Treatment Label', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('An optional heading that appears on the single post page above the detail content. If left blank it falls back to the default label set in Settings, or "Treatments Used" if nothing is set there.', 'll-bag'); ?></dd>

          <dt><?php esc_html_e('Detail Sections', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('Add one or more blocks of information about this case — things like the treatment performed, timeframe, or provider notes. Each section has a Title, a main Content area, and an optional Read More area that opens in a popup for longer content. If you add more than one section, they\'ll automatically display as tabs on the post page. You can add up to three sections.', 'll-bag'); ?></dd>
        </dl>

        <h3><?php esc_html_e('Images Tab', 'll-bag'); ?></h3>
        <p><?php esc_html_e('Click "Add Image" to add your first entry. You can add as many as you need — the first one will be used as the thumbnail on the gallery card.', 'll-bag'); ?></p>
        <p><?php esc_html_e('For each entry, choose one of three types from the Image Options dropdown:', 'll-bag'); ?></p>
        <dl class="ll-bag-how-to__def-list">
          <dt><?php esc_html_e('One Image', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('Upload a single image — typically a combined before/after photo in one frame. The image will display in its original proportions.', 'll-bag'); ?></dd>

          <dt><?php esc_html_e('Two Images', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('Upload a separate before and after image. You\'ll also choose a crop ratio so both images align correctly, and optionally enable a comparison slider so visitors can drag to compare the two. The slider only appears on the single post page — on gallery cards the two images sit side by side. For best results, use images cropped the same way with the subject in the same position in both photos.', 'll-bag'); ?></dd>

          <dt><?php esc_html_e('Video', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('Paste in a video URL and add a short title for accessibility. The video will be embedded in place of an image.', 'll-bag'); ?></dd>
        </dl>

        <h3><?php esc_html_e('Settings Tab', 'll-bag'); ?></h3>
        <p><?php esc_html_e('Sensitive Images', 'll-bag'); ?></p>
        <p><?php esc_html_e('Toggle this on if the post contains sensitive before & after content. Visitors will see the images blurred by default and be given the option to reveal them. This applies to both the gallery card, the single post page, and the Before and After components. The text shown inside that popup can be customized under Before & Afters → Settings → NSFW Popup.', 'll-bag'); ?></p>

        <h3><?php esc_html_e('Categorizing Your Post', 'll-bag'); ?></h3>
        <p><?php esc_html_e('On the right side of the edit screen you\'ll see panels for each of your active filters — things like Categories, Providers, or any custom filters you\'ve set up. Check the terms that apply to this post. These are what visitors use to filter the gallery, and they also power the "Related Before & Afters" suggestions on the single post page.', 'll-bag'); ?></p>

        <h3><?php esc_html_e('Provider Settings', 'll-bag'); ?></h3>
        <p><?php esc_html_e('If Providers is one of your active filters, each provider term has two extra fields available when you edit it — go to Before & Afters → Providers and click on a provider to see them.', 'll-bag'); ?></p>
        <dl class="ll-bag-how-to__def-list">
          <dt><?php esc_html_e('Link', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('A URL for this provider — typically a link to their bio or profile page. This appears on single post pages where that provider has been assigned.', 'll-bag'); ?></dd>

          <dt><?php esc_html_e('Image', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('A photo of the provider, also shown on single post pages alongside the link.', 'll-bag'); ?></dd>
        </dl>

        <h3><?php esc_html_e('Related Before & After Posts', 'll-bag'); ?></h3>
        <p><?php esc_html_e('Near the bottom of the edit screen you\'ll find a "Related Before & After Posts" box. This controls which posts are suggested to visitors after they view this one.', 'll-bag'); ?></p>

        <img
          src="<?= esc_url(LL_BAG_URL . 'resources/img/before-after__related-posts.png') ?>"
          alt="<?php esc_attr_e('Screenshot of the Related Before & After Posts meta box', 'll-bag'); ?>"
          class="ll-bag-how-to__screenshot"
        >
        <p><?php esc_html_e('By default, the gallery automatically surfaces related posts based on the tags assigned to the current post — no action needed. If you want to override that and point to a specific set of posts instead, use this box to select the terms that should drive the suggestions. Switch between your filter categories using the tabs on the left of the box, then check whichever terms apply.', 'll-bag'); ?></p>
        <p><?php esc_html_e('Leave everything unchecked to use the automatic behavior.', 'll-bag'); ?></p>

        <h3><?php esc_html_e('Global Settings for Single Posts', 'll-bag'); ?></h3>
        <p><?php esc_html_e('A few things that appear on every single post page are controlled globally rather than per-post. You\'ll find them under Before & Afters → Settings, on the Single Page tab.', 'll-bag'); ?></p>
        <dl class="ll-bag-how-to__def-list">
          <dt><?php esc_html_e('Default Single Page Label', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('The heading that appears above the treatment details on every post. Individual posts can override this with their own Treatment Label — but if they don\'t, this is what shows. Leave it blank and it will fall back to "Treatments Used".', 'll-bag'); ?></dd>

          <dt><?php esc_html_e('CTA Title & CTA Link', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('A call-to-action button that appears on every single post page — typically something like "Book a Consultation" linking to a contact page. Fill in both the title and the link to show the CTA. Leave either field blank and the CTA won\'t appear at all.', 'll-bag'); ?></dd>

          <dt><?php esc_html_e('Related Treatments Slider Title', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('The heading shown above the row of related before & after posts at the bottom of the single post page. Set it to whatever fits your site — something like "More Before & Afters" or "You Might Also Like".', 'll-bag'); ?></dd>

          <dt><?php esc_html_e('Card Background Color', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('The background color visible behind each gallery card. Found under Before & Afters → Settings → Card Background Color.', 'll-bag'); ?></dd>
        </dl>
      </section>

      <section id="archive-page" class="ll-bag-how-to__section">
        <h2><?php esc_html_e('Archive Page', 'll-bag'); ?></h2>

        <p><?php esc_html_e('The archive page is where all your before & after posts live — the main gallery visitors browse and filter. Setting it up takes a few steps across two different areas of WordPress.', 'll-bag'); ?></p>

        <ol class="ll-bag-how-to__steps">
          <li>
            <strong><?php esc_html_e('Create a page.', 'll-bag'); ?></strong>
            <?php esc_html_e('Go to Pages → Add New and create a page with whatever title you want visitors to see (e.g. "Before & Afters"). You don\'t need to add any content — just publish it.', 'll-bag'); ?>
          </li>
          <li>
            <strong><?php esc_html_e('Assign the page in Archive Settings.', 'll-bag'); ?></strong>
            <?php esc_html_e('Go to Before & Afters → Settings and open the Archive Settings tab. Find the "All Posts Archive Page" field and select the page you just created.', 'll-bag'); ?>
          </li>
          <li>
            <strong><?php esc_html_e('Save your permalinks.', 'll-bag'); ?></strong>
            <?php esc_html_e('Go to Settings → Permalinks and click Save Changes — you don\'t need to change anything, just save. This tells WordPress to recognize the new page as the gallery archive.', 'll-bag'); ?>
          </li>
          <li>
            <strong><?php esc_html_e('Edit the archive page.', 'll-bag'); ?></strong>
            <?php esc_html_e('Go back to the page you created and open it for editing. You\'ll now see a "Before & After Archive" section with fields for the hero banner — including a content area, an optional link, and a background image. Fill these in to customize the top of your gallery page.', 'll-bag'); ?>
          </li>
        </ol>

        <p><?php esc_html_e('Once the page is assigned and permalinks are saved, the gallery will automatically display on that page — no template editing or shortcodes required. Any components added to this page will appear below the gallery.', 'll-bag'); ?></p>
      </section>

      <section id="category-page" class="ll-bag-how-to__section">
        <h2><?php esc_html_e('Taxonomy Archive', 'll-bag'); ?></h2>

        <p><?php esc_html_e('The taxonomy archive is an optional page that displays your filter categories as a browsable grid — for example, a page showing all your treatment types that visitors can click into. The category shown is whichever taxonomy you\'ve designated as the Featured Taxonomy in settings.', 'll-bag'); ?></p>

        <h3><?php esc_html_e('Configuring the taxonomy archive', 'll-bag'); ?></h3>
        <p><?php esc_html_e('All of these settings live under Before & Afters → Settings → Taxonomy Archive Settings.', 'll-bag'); ?></p>
        <ol class="ll-bag-how-to__steps">
          <li>
            <strong><?php esc_html_e('Enable the taxonomy archive.', 'll-bag'); ?></strong>
            <?php esc_html_e('Toggle "Use Taxonomy Archive?" on. This activates the page and its URL routing.', 'll-bag'); ?>
          </li>
          <li>
            <strong><?php esc_html_e('Select a Featured Taxonomy.', 'll-bag'); ?></strong>
            <?php esc_html_e('Choose which of your filters will be used as the category grid. The dropdown is populated from the filters you\'ve set up in Filter Settings — so if you don\'t see the one you want, add it there first.', 'll-bag'); ?>
          </li>
          <li>
            <strong><?php esc_html_e('Set a URL slug.', 'll-bag'); ?></strong>
            <?php esc_html_e('The slug controls the URL for the archive page (e.g. a slug of "treatments" would produce /before-afters/treatments/). After changing it, go to Settings → Permalinks and click Save Changes to apply the update.', 'll-bag'); ?>
          </li>
          <li>
            <strong><?php esc_html_e('Fill in the hero and subtitle.', 'll-bag'); ?></strong>
            <?php esc_html_e('The Taxonomy Archive Hero fields let you add a banner at the top of the page with content, an optional link, and a background image. The Taxonomy Archive Subtitle appears above the category grid as a short line of introductory text.', 'll-bag'); ?>
          </li>
        </ol>

        <h3><?php esc_html_e('Adding images to category cards', 'll-bag'); ?></h3>
        <p><?php esc_html_e('Each card in the category grid can have its own background image. Once you\'ve selected a Featured Taxonomy in settings, a background image field will appear whenever you edit a term within that taxonomy.', 'll-bag'); ?></p>
        <p><?php esc_html_e('To add one, go to the taxonomy list for your selected filter (e.g. Before & Afters → Treatments), click on a term to edit it, and you\'ll find a "Background Image" field under the Before & After Category Settings section. Upload an image there and it will be used as the card background on the category archive page.', 'll-bag'); ?></p>
      </section>

    </div>
  </div>
</div>
