<?php
use Timber\Timber;

/**
 * Check if class exists before redefining it
 */
if (!class_exists('Timber_Acf_Wp_Blocks')) {
	/**
	 * Main Timber_Acf_Wp_Block Class
	 */
	class Timber_Acf_Wp_Blocks
	{
		/**
		 * Constructor
		 */
		public function __construct()
		{
			if (
				is_callable('add_action')
				&& is_callable('acf_register_block_type')
				&& class_exists('Timber')
			) {
				add_action('acf/init', array(__CLASS__, 'timber_block_init'), 10, 0);
			} elseif (is_callable('add_action')) {
				add_action(
					'admin_notices',
					function () {
						echo '<div class="error"><p>Timber ACF WP Blocks requires Timber and ACF.';
						echo 'Check if the plugins or libraries are installed and activated.</p></div>';
					}
				);
			}
		}


		/**
		 * Create blocks based on templates found in Timber's "views/blocks" directory
		 */
		public static function timber_block_init()
		{
			// Get an array of directories containing blocks.
			$directories = self::timber_block_directory_getter();

			// Check whether ACF exists before continuing.
			foreach ($directories as $dir) {
				// Sanity check whether the directory we're iterating over exists first.
				if (!file_exists(\locate_template($dir))) {
					return;
				}

				// Iterate over the directories provided and look for templates.
				$template_directory = new DirectoryIterator(\locate_template($dir));
				foreach ($template_directory as $template) {

					if ($template->isDot() || $template->isDir()) {
						continue;
					}

					$file_parts = pathinfo($template->getFilename());
					if ('json' !== $file_parts['extension']) {
						continue;
					}

					// Strip the file extension to get the slug.
					$file_dir_array = explode('/', $dir);
					$slug = end($file_dir_array);
					$path = get_stylesheet_directory() . '/views/blocks/' . $slug;

					// Register the block with ACF.
					register_block_type($path);
				}
			}
		}

		/**
		 * Callback to register blocks
		 *
		 * @param array  $block      stores all the data from ACF.
		 * @param string $content    content passed to block.
		 * @param bool   $is_preview checks if block is in preview mode.
		 * @param int    $post_id    Post ID.
		 */
		public static function timber_blocks_callback($block, $content = '', $is_preview = false, $post_id = 0)
		{
			// Context compatibility.
			if (method_exists('Timber', 'context')) {
				$context = Timber::context();
			} else {
				$context = Timber::get_context();
			}

			// Set up the slug to be useful.
			$slug = str_replace('acf/', '', $block['name']);

			$context['block'] = $block;
			$context['post_id'] = $post_id;
			$context['slug'] = $slug;
			$context['is_preview'] = $is_preview;
			$context['fields'] = \get_fields();
			$classes = array_merge(
				array($slug),
				isset($block['className']) ? array($block['className']) : array(),
				$is_preview ? array('is-preview') : array(),
				array('align' . $context['block']['align'])
			);

			$context['classes'] = implode(' ', $classes);

			$is_example = false;

			if (!empty($block['data']['is_example'])) {
				$is_example = true;
				$context['fields'] = $block['data'];
			}

			$context = apply_filters('timber/acf-gutenberg-blocks-data', $context);
			$context = apply_filters('timber/acf-gutenberg-blocks-data/' . $slug, $context);
			$context = apply_filters('timber/acf-gutenberg-blocks-data/' . $block['id'], $context);

			$paths = self::timber_acf_path_render($slug, $is_preview, $is_example);

			Timber::render($paths, $context);
		}

		/**
		 * Generates array with paths and slugs
		 *
		 * @param string $slug       File slug.
		 * @param bool   $is_preview Checks if preview.
		 * @param bool   $is_example Checks if example.
		 */
		public static function timber_acf_path_render($slug, $is_preview, $is_example)
		{
			$directories = self::timber_block_directory_getter();

			$ret = array();

			/**
			 * Filters the name of suffix for example file.
			 *
			 * @since 1.12
			 */
			$example_identifier = apply_filters('timber/acf-gutenberg-blocks-example-identifier', '-example');

			/**
			 * Filters the name of suffix for preview file.
			 *
			 * @since 1.12
			 */
			$preview_identifier = apply_filters('timber/acf-gutenberg-blocks-preview-identifier', '-preview');

			foreach ($directories as $directory) {
				if ($is_example) {
					$ret[] = $directory . "/{$slug}{$example_identifier}.twig";
				}
				if ($is_preview) {
					$ret[] = $directory . "/{$slug}{$preview_identifier}.twig";
				}
				$ret[] = $directory . "/{$slug}.twig";
			}

			return $ret;
		}

		/**
		 * Generates the list of subfolders based on current directories
		 *
		 * @param array $directories File path array.
		 */
		public static function timber_blocks_subdirectories($directories)
		{
			$ret = array();

			foreach ($directories as $base_directory) {
				// Check if the folder exist.
				if (!file_exists(\locate_template($base_directory))) {
					continue;
				}

				$template_directory = new RecursiveDirectoryIterator(
					\locate_template($base_directory),
					FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_SELF
				);

				if ($template_directory) {
					foreach ($template_directory as $directory) {
						if ($directory->isDir() && !$directory->isDot()) {
							$ret[] = $base_directory . '/' . $directory->getFilename();
						}
					}
				}
			}

			return $ret;
		}

		/**
		 * Universal function to handle getting folders and subfolders
		 */
		public static function timber_block_directory_getter()
		{
			// Get an array of directories containing blocks.
			$directories = apply_filters('timber/acf-gutenberg-blocks-templates', array('views/blocks'));

			// Check subfolders.
			$subdirectories = self::timber_blocks_subdirectories($directories);

			if (!empty($subdirectories)) {
				$directories = array_merge($directories, $subdirectories);
			}

			return $directories;
		}

		/**
		 * Default options setter.
		 *
		 * @param  [array] $data - header set data.
		 * @return [array]
		 */
		public static function timber_block_default_data($data)
		{
			$default_data = apply_filters('timber/acf-gutenberg-blocks-default-data', array());
			$data_array = array();

			if (!empty($data['default_data'])) {
				$default_data_key = $data['default_data'];
			}

			if (isset($default_data_key) && !empty($default_data[$default_data_key])) {
				$data_array = $default_data[$default_data_key];
			} elseif (!empty($default_data['default'])) {
				$data_array = $default_data['default'];
			}

			if (is_array($data_array)) {
				$data = array_merge($data_array, $data);
			}

			return $data;
		}
	}
}

if (is_callable('add_action')) {
	add_action(
		'after_setup_theme',
		function () {
			new Timber_Acf_Wp_Blocks();
		}
	);
}
