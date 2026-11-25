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
            <?php esc_html_e('Gallery Page', 'll-bag'); ?>
          </a>
        </li>
        <li>
          <a href="#category-page" class="ll-bag-how-to__nav-link">
            <?php esc_html_e('Categories Page', 'll-bag'); ?>
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
            <?php esc_html_e('Make a new WordPress page, then choose it as the Gallery Page under Before & Afters → Settings → Archive Settings.', 'll-bag'); ?>
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

          <dt><?php esc_html_e('Tag Label', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('Appears once "Show on Cards" is checked. When a card has more than one tag, the card shows a single "Multiple" pill instead of listing every tag — this field sets the word that follows "Multiple" (e.g. "Categories"), telling visitors this before and after uses multiple of that tag type.', 'll-bag'); ?></dd>
        </dl>

        <h3><?php esc_html_e('Adding a new filter', 'll-bag'); ?></h3>
        <ol class="ll-bag-how-to__steps">
          <li><?php esc_html_e('Click the "+ Add Filter" button at the top of the page.', 'll-bag'); ?></li>
          <li><?php esc_html_e('A new row will appear at the bottom of the list. Type a name for your filter in the Label field.', 'll-bag'); ?></li>
          <li><?php esc_html_e('Check the options you want for this filter using the checkboxes.', 'll-bag'); ?></li>
          <li><?php esc_html_e('Click "Save Filters" when you\'re done. Your changes won\'t be applied until you save.', 'll-bag'); ?></li>
        </ol>

        <h3><?php esc_html_e('Adding options to a filter', 'll-bag'); ?></h3>
        <p><?php esc_html_e('Setting up a filter only creates the heading visitors see in the sidebar — it starts out empty. The choices underneath it (the checkboxes) are the individual terms, and you add those from the left-hand menu, not from this page.', 'll-bag'); ?></p>
        <ol class="ll-bag-how-to__steps">
          <li><?php esc_html_e('In the left-hand admin menu, go to Before & Afters and click the filter you want to fill in — each filter you enable gets its own entry there (e.g. Before & Afters → Treatments).', 'll-bag'); ?></li>
          <li><?php esc_html_e('Add one term per choice you want visitors to be able to filter by, using the "Add New" form on the left of that screen.', 'll-bag'); ?></li>
          <li><?php esc_html_e('Repeat for each filter. A filter with no terms, or whose terms are not used by any published post, is hidden from the gallery sidebar automatically.', 'll-bag'); ?></li>
        </ol>
        <p><?php esc_html_e('Terms are assigned to individual before & after posts on the post edit screen — see Adding Posts below. Only terms actually used by a post appear as filter options.', 'll-bag'); ?></p>

        <h3><?php esc_html_e('Reordering filters', 'll-bag'); ?></h3>
        <p><?php esc_html_e('Grab the dotted handle on the far left of any row and drag it up or down to change the order filters appear in the sidebar. Click "Save Filters" to keep the new order.', 'll-bag'); ?></p>

        <h3><?php esc_html_e('Removing a filter', 'll-bag'); ?></h3>
        <p><?php esc_html_e('Click the "Remove" button on the right side of the row, then click "Save Filters". Note that the built-in Categories filter does not have a Remove button — it is managed through WordPress directly.', 'll-bag'); ?></p>

        <h3><?php esc_html_e('Setting up Categories', 'll-bag'); ?></h3>
        <p><?php echo wp_kses_post(sprintf(
          /* translators: %s: link to the Categories Page section */
          __('Whichever filter you choose as the <strong>Featured Taxonomy</strong> — under Before & Afters → Settings → Archive Settings — is treated as each post\'s category, and gains extra fields once you\'ve made that selection. Go to that filter\'s term list (e.g. Before & Afters → Treatments) and edit any term: you\'ll see a Background Image field, used for the term\'s card on the categories page, and CTA fields that override the default CTA on that category\'s posts. See %s for the full setup.', 'll-bag'),
          '<a href="#category-page">' . esc_html__('Categories Page', 'll-bag') . '</a>'
        )); ?></p>
        <p><?php esc_html_e('Note that this is a separate setting from "Show on Cards" in the table above — a filter can be used for gallery card pills, the Featured Taxonomy, both, or neither, independently of one another.', 'll-bag'); ?></p>
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
          <dt><?php esc_html_e('Single Page Label', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('An optional heading that appears on the single post page above the detail content. If left blank it falls back to the default label set in Settings; if that is empty too, no heading is shown.', 'll-bag'); ?></dd>

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
          <dd><?php esc_html_e('Upload a separate before and after image. You\'ll also choose a crop ratio so both images align correctly — "Site default" (preselected) follows the Default Image Ratio under Settings → Display — and optionally enable a comparison slider so visitors can drag to compare the two. The slider only appears on the single post page — on gallery cards the two images sit side by side. For best results, use images cropped the same way with the subject in the same position in both photos.', 'll-bag'); ?></dd>

          <dt><?php esc_html_e('Video', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('Paste in a video URL and add a short title for accessibility. The video will be embedded in place of an image.', 'll-bag'); ?></dd>
        </dl>

        <h3><?php esc_html_e('Settings Tab', 'll-bag'); ?></h3>
        <p><?php esc_html_e('Sensitive Images', 'll-bag'); ?></p>
        <p><?php esc_html_e('Toggle this on if the post contains sensitive before & after content. Visitors will see the images blurred by default and be given the option to reveal them. This applies to both the gallery card, the single post page, and the Before and After components. The text shown inside that popup can be customized under Before & Afters → Settings → Display → Sensitive Content Popup Text.', 'll-bag'); ?></p>

        <h3><?php esc_html_e('Categorizing Your Post', 'll-bag'); ?></h3>
        <p><?php esc_html_e('On the right side of the edit screen you\'ll see panels for each of your active filters — things like Categories, Providers, or any custom filters you\'ve set up. Check the terms that apply to this post. These are what visitors use to filter the gallery, and they also decide which related posts are suggested on the single post page.', 'll-bag'); ?></p>

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

        <p><?php esc_html_e('Related posts are chosen automatically, so there is nothing you need to do. The gallery looks at the terms you checked for this post (Category, Treatment, Provider and any other filters you use) and shows the six posts that have the most in common with it. Terms that only a few posts share count for more than terms that most posts share, so a specific match like a treatment name beats a broad one. If several posts match equally well, the ones closest to this post in the gallery order come first. If fewer than six posts share anything with this one, the empty spots are filled with the newest posts.', 'll-bag'); ?></p>
        <p><?php esc_html_e('To pick the matches yourself, use the tabs on the left of the box and check the terms that should drive the suggestions. Posts with any of those terms are shown first, and the automatic matches fill whatever spots are left. Leave everything unchecked to use the automatic behavior only.', 'll-bag'); ?></p>

        <h3><?php esc_html_e('Global Settings for Single Posts', 'll-bag'); ?></h3>
        <p><?php esc_html_e('A few things that appear on every single post page are controlled globally rather than per-post. You\'ll find them under Before & Afters → Settings, on the Single Pages tab.', 'll-bag'); ?></p>
        <dl class="ll-bag-how-to__def-list">
          <dt><?php esc_html_e('Default Page Label', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('The heading that appears above the detail content on every post. Individual posts can override this with their own Single Page Label — but if they don\'t, this is what shows. Leave it blank and no heading is shown.', 'll-bag'); ?></dd>

          <dt><?php esc_html_e('Default CTA', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('A call-to-action button that appears on every single post page — typically something like "Book a Consultation" linking to a contact page. Fill in both the title and the link to show the CTA. Leave either field blank and the CTA won\'t appear at all. A category can override it with its own CTA from that term\'s edit screen.', 'll-bag'); ?></dd>

          <dt><?php esc_html_e('Related Posts Slider Title', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('The heading shown above the row of related before & after posts at the bottom of the single post page. Set it to whatever fits your site — something like "More Before & Afters" or "You Might Also Like".', 'll-bag'); ?></dd>
        </dl>

        <h3><?php esc_html_e('Display Settings', 'll-bag'); ?></h3>
        <p><?php esc_html_e('Settings that apply everywhere the gallery appears — cards, sliders and single pages — live under Before & Afters → Settings, on the Display tab.', 'll-bag'); ?></p>
        <dl class="ll-bag-how-to__def-list">
          <dt><?php esc_html_e('Default Image Ratio', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('The crop frame for every image set to "Site default": 16/9 Wide, 1/1 Square, 3/1 Panorama, 4/5 Vertical, or Custom (enter width:height, e.g. 4:3). Changing it updates every "Site default" image at once; images with their own ratio keep it. Re-check focal points on a few posts after changing it.', 'll-bag'); ?></dd>

          <dt><?php esc_html_e('Sensitive Content Popup Text', 'll-bag'); ?></dt>
          <dd><?php esc_html_e('The message shown over posts marked as sensitive, on gallery cards and single pages.', 'll-bag'); ?></dd>
        </dl>
      </section>

      <section id="archive-page" class="ll-bag-how-to__section">
        <h2><?php esc_html_e('Gallery Page', 'll-bag'); ?></h2>

        <p><?php esc_html_e('The gallery page is where visitors browse your before & afters. Setting it up takes a few steps.', 'll-bag'); ?></p>

        <ol class="ll-bag-how-to__steps">
          <li>
            <strong><?php esc_html_e('Create a page.', 'll-bag'); ?></strong>
            <?php esc_html_e('Go to Pages → Add New and create a page with whatever title you want visitors to see (e.g. "Before & Afters"). You don\'t need to add any content — just publish it.', 'll-bag'); ?>
          </li>
          <li>
            <strong><?php esc_html_e('Assign the page.', 'll-bag'); ?></strong>
            <?php esc_html_e('Go to Before & Afters → Settings → Archive Settings and select it in the Gallery Page field.', 'll-bag'); ?>
          </li>
          <li>
            <strong><?php esc_html_e('Choose the Main Gallery View.', 'll-bag'); ?></strong>
            <?php esc_html_e('Posts archive: the page shows every before & after with the filter sidebar, and there is no categories page. Categories: the page shows the category grid, and the full posts archive moves to its own URL, set by Posts Archive Slug (default "all", e.g. /before-afters/all/). The Gallery URLs box shows where each page lives. URL changes apply automatically when you save — no need to re-save Permalinks.', 'll-bag'); ?>
          </li>
          <li>
            <strong><?php esc_html_e('Edit the gallery page.', 'll-bag'); ?></strong>
            <?php esc_html_e('Open the page you created for editing. You\'ll see a "Before & After Archive" section with fields for the hero banner — a content area, an optional link, and a background image.', 'll-bag'); ?>
          </li>
        </ol>

        <p><?php esc_html_e('Once the page is assigned, the gallery displays on it automatically — no template editing or shortcodes required. Any components added to this page will appear below the gallery.', 'll-bag'); ?></p>
      </section>

      <section id="category-page" class="ll-bag-how-to__section">
        <h2><?php esc_html_e('Categories Page', 'll-bag'); ?></h2>

        <p><?php esc_html_e('The categories page displays your categories as a browsable grid — for example, all your treatment types, each linking to its filtered posts. It uses whichever filter is set as the Featured Taxonomy, and is only available when Main Gallery View is set to Categories, where it becomes the main gallery page.', 'll-bag'); ?></p>

        <h3><?php esc_html_e('Configuring the categories page', 'll-bag'); ?></h3>
        <p><?php esc_html_e('These settings live under Before & Afters → Settings → Categories Page.', 'll-bag'); ?></p>
        <ol class="ll-bag-how-to__steps">
          <li>
            <strong><?php esc_html_e('Pick the Featured Taxonomy.', 'll-bag'); ?></strong>
            <?php esc_html_e('Under Archive Settings, choose the filter used for the category grid. The dropdown lists the filters from Filter Settings — if the one you want is missing, add it there first.', 'll-bag'); ?>
          </li>
          <li>
            <strong><?php esc_html_e('Fill in the hero and subtitle.', 'll-bag'); ?></strong>
            <?php esc_html_e('The Hero fields add a banner at the top of the page with content, an optional link, and a background image. The Subtitle appears above the category grid.', 'll-bag'); ?>
          </li>
          <li>
            <strong><?php esc_html_e('Optional: set up Hero Navigation.', 'll-bag'); ?></strong>
            <?php esc_html_e('Each row (Label, Taxonomy, Term) adds a pill to the hero — e.g. Breast / Face / Body from Body Area. Clicking a pill opens a page showing only the categories used by posts tagged with that term, at /before-afters/categories/breast/. Show "All" Pill adds a first pill that returns to the full grid, and Pill Pages Slug sets the "categories" part of those URLs.', 'll-bag'); ?>
          </li>
        </ol>

        <h3><?php esc_html_e('Adding images to category cards', 'll-bag'); ?></h3>
        <p><?php esc_html_e('Each card in the category grid can have its own background image. Go to the term list for your Featured Taxonomy (e.g. Before & Afters → Treatments), click a term to edit it, and upload an image in the "Background Image" field under Before & After Category Settings.', 'll-bag'); ?></p>
      </section>

    </div>
  </div>
</div>
