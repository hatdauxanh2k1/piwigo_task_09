<?php
defined('EXTENDED_DESC_PATH') or die('Hacking attempt!');

/**
 * Return html code for  category thumb
 */
function extdesc_get_cat_thumb($elem_id)
{
  global $template, $user;


  $elem_id = intval($elem_id);
  if ($elem_id <= 0) {
    return '';
  }
  $query = '
  SELECT
  cat.id,
  cat.name,
  cat.comment,
  cat.representative_picture_id,
  cat.permalink,
  uc.nb_images,
  uc.count_images,
  uc.count_categories,
  img.path
    FROM ' . CATEGORIES_TABLE . ' AS cat
  INNER JOIN ' . USER_CACHE_CATEGORIES_TABLE . ' AS uc
    ON cat.id = uc.cat_id
  INNER JOIN ' . IMAGES_TABLE . ' AS img
    ON img.id = uc.user_representative_picture_id
  WHERE cat.id = ' . $elem_id . ';';


  $result = pwg_query($query);
  $category = pwg_db_fetch_assoc($result);

  if ($category === NULL) {
    $category = load_from_cache($elem_id);
    if ($category === NULL) {
      // Nếu cache cũng không có dữ liệu, thực hiện logic xử lý mặc định
      $category = [];
      save_to_cache($elem_id, $category);
    }
  } else {
    save_to_cache($elem_id, $category);
  }


  if ($result and $category) {

    $p = array(
      'ID'    => $category['id'],
      'TN_SRC'   => $category['path'],
      'TN_ALT'   => strip_tags($category['name']),
      'URL'   => make_index_url(array('category' => $category)),
      'CAPTION_NB_IMAGES' => get_display_images_count(
        $category['nb_images'],
        $category['count_images'],
        $category['count_categories'],
        true,
        '<br />'
      ),
      'DESCRIPTION' =>
      trigger_change(
        'render_category_literal_description',
        trigger_change(
          'render_category_description',
          @$category['comment'],
          'subcatify_category_description'
        )
      ),
      'NAME'  => trigger_change(
        'render_category_name',
        $category['name'],
        'subcatify_category_name'
      ),
    );
    $template->assign(
      $p
    );

    $template->set_filename('extended_description_content', realpath(EXTENDED_DESC_PATH . 'template/cat.tpl'));
    return $template->parse('extended_description_content', true);
  }
  return '';
}

/**
 * Return html code for a photo
 *
 * @int    id:    picture id
 * @int    album: album to display picture in    (default: null)
 * @string size:  picture size                   (default: M)
 * @bool   html:  return complete html structure (default: true)
 * @bool   link:  add a link to the picture      (default: true)
 */
function extdesc_get_photo_sized($param)
{
  global $template;

  $default_params = array(
    'id' =>    array('\d+', null),
    'album' => array('\d+', null),
    'size' =>  array(extdesc_get_deriv_regex(), 'M'),
    'html' =>  array('boolean', true),
    'link' =>  array('boolean', true),
  );

  $params = extdesc_parse_parameters($param, $default_params);

  // check picture id
  if (empty($params['id'])) return 'missing picture id';

  // parameters
  $deriv_type = extdesc_get_deriv_type($params['size']);

  // get picture
  $query = 'SELECT * FROM ' . IMAGES_TABLE . ' WHERE id = ' . $params['id'] . ';';
  $result = pwg_query($query);

  if (pwg_db_num_rows($result)) {
    $picture = pwg_db_fetch_assoc($result);

    // url
    if ($params['link']) {
      if (!empty($params['album'])) {
        $query = '
SELECT id, name, permalink
  FROM ' . CATEGORIES_TABLE . '
  WHERE id = ' . $params['album'] . '
;';
        $category = pwg_db_fetch_assoc(pwg_query($query));

        $url = make_picture_url(array(
          'image_id' => $picture['id'],
          'category' => array(
            'id' => $category['id'],
            'name' => $category['name'],
            'permalink' => $category['permalink'],
          )
        ));
      } else {
        $url = make_picture_url(array('image_id' => $picture['id']));
      }
    }

    // image
    $src_image = new SrcImage($picture);
    $derivatives = DerivativeImage::get_all($src_image);
    $selected_derivative = $derivatives[$deriv_type];

    $template->assign(array(
      'ed_image' => array(
        'selected_derivative' => $selected_derivative,
        'ALT_IMG' => $picture['file'],
      )
    ));

    // output
    if ($params['html']) {
      $template->set_filename('extended_description_content', realpath(EXTENDED_DESC_PATH . 'template/picture_content.tpl'));
      $content = $template->parse('extended_description_content', true);

      if ($params['link']) return '<a href="' . $url . '">' . $content . '</a>';
      else                 return $content;
    } else {
      return $selected_derivative->get_url();
    }
  }

  return 'invalid picture id';
}

/**
 * Return html code for a random photo
 *
 * @int    album: select picture from this album (default: all)
 * @string size:  picture size                   (default: M)
 * @bool   html:  return complete html structure (default: true)
 * @bool   link:  add a link to the picture      (default: false)
 * @int    nb_images: number of picture              (default: 1)
 */
function extdesc_get_random_photo($param)
{
  $default_params = array(
    'album' => array('\d+', null),
    'cat' =>   array('\d+', null), // historical
    'size' =>  array(extdesc_get_deriv_regex(), 'M'),
    'html' =>  array('boolean', true),
    'link' =>  array('boolean', false),
    'nb_images' => array('\d+', 1),
  );

  $params = extdesc_parse_parameters($param, $default_params);

  // check album id
  if (empty($params['album']) and !empty($params['cat'])) {
    $params['album'] = $params['cat'];
  }

  // get picture id
  $query = '
SELECT id, category_id
  FROM ' . IMAGES_TABLE . '
    JOIN ' . IMAGE_CATEGORY_TABLE . ' ON image_id = id';
  if (empty($params['album'])) {
    $query .= '
  WHERE 1=1 '
      . get_sql_condition_FandF(
        array(
          'forbidden_categories' => 'category_id',
          'visible_categories' => 'category_id',
          'visible_images' => 'id'
        ),
        'AND'
      );
  } else {
    $query .= '
  WHERE category_id = ' . $params['album'];
  }

  $query .= '
  ORDER BY ' . DB_RANDOM_FUNCTION . '()
  LIMIT ' . $params['nb_images'] . '
;';
  $result = pwg_query($query);

  if (pwg_db_num_rows($result)) {
    if (1 == $params['nb_images']) {
      list($params['id'], $params['album']) = pwg_db_fetch_row($result);
      return extdesc_get_photo_sized($params);
    } else {
      $random_images = array();
      $random_image_info = array();

      while ($row = pwg_db_fetch_assoc($result)) {
        $params['id'] = $row['id'];
        $params['album'] = $row['category_id'];

        $random_images[] = '<li>' . extdesc_get_photo_sized($params) . '</li>';
      }

      array_unshift($random_images, '<ul class="extdesc-random-thumbnails">');
      array_push($random_images, '</ul>');

      global $template;
      $template->func_combine_css(array('path' => EXTENDED_DESC_PATH . 'template/thumbnails.css'));

      return implode(' ', $random_images);
    }
  }

  return '';
}

/**
 * Return html code for a nivo slider (album or list is mandatory)
 *
 * @int    album:     select pictures from this album
 * @int    nb_images: display only x pictures           (default: 10)
 * @string random:    random sort order                 (default: no)
 *
 * @string list:      pictures id separated by a comma
 *
 * @string size:      picture size                      (default: M)
 * @int    speed:     slideshow duration                (default: 3)
 * @bool   title:     display picture name              (default: false)
 * @string effect:    transition effect                 (default: fade)
 * @bool   arrows:    display navigation arrows         (default: true)
 * @string control:   display navigation bar            (default: true)
 * @bool   elastic:   adapt slider size to each picture (default: true)
 * @int thumbs_size:  size of thumbnails if control=thumb (default: 80)
 */
function extdesc_get_slider($param)
{
  global $template, $conf;

  $default_params = array(
    'album' =>     array('\d+', null),
    'nb_images' => array('\d+', 10),
    'random' =>    array('boolean', false),
    'list' =>      array('[\d,]+', null),
    'size' =>      array(extdesc_get_deriv_regex(), 'M'),
    'speed' =>     array('\d+', 5),
    'title' =>     array('boolean', false),
    'effect' =>    array('[a-zA-Z]+', 'fade'),
    'arrows' =>    array('boolean', true),
    'control' =>   array('yes|no|true|false|thumb', true),
    'elastic' =>   array('boolean', true),
    'thumbs_size' => array('\d+', 80),
  );

  $params = extdesc_parse_parameters($param, $default_params);

  // check size
  $deriv_type = extdesc_get_deriv_type($params['size']);
  $enabled = ImageStdParams::get_defined_type_map();
  if (empty($enabled[$deriv_type])) {
    return '(nivoSlider) size disabled';
  }

  // parameters
  if ($params['control'] === 'thumb') {
    $params['control'] = true;
    $params['control_thumbs'] = true;
  } else {
    $params['control'] = filter_var($params['control'], FILTER_VALIDATE_BOOLEAN);
    $params['control_thumbs'] = false;
  }

  $tpl_vars = $params;

  // pictures from album...
  if (!empty($params['album'])) {
    $query = '
SELECT name, image_order
  FROM ' . CATEGORIES_TABLE . '
  WHERE id = ' . $params['album'] . '
;';
    list($album_name, $order_by) = pwg_db_fetch_row(pwg_query($query));

    if ($params['random']) {
      $order_by = DB_RANDOM_FUNCTION . '()';
    } else if (empty($order_by)) {
      $order_by = str_replace('ORDER BY ', '', $conf['order_by_inside_category']);
    }

    // get pictures ids
    $query = '
SELECT image_id
  FROM ' . IMAGE_CATEGORY_TABLE . ' as ic
    INNER JOIN ' . IMAGES_TABLE . ' as i
    ON i.id = ic.image_id
  WHERE category_id = ' . $params['album'] . '
  ORDER BY ' . $order_by . '
  LIMIT ' . $params['nb_images'] . '
;';
    $ids = array_from_query($query, 'image_id');
    if (empty($ids)) {
      return '(nivoSlider) no photos in album #' . $params['album'];
    }
    $ids = implode(',', $ids);
  }
  // ...or pictures list
  else if (empty($params['list'])) {
    return '(nivoSlider) missing album id or photos list';
  } else {
    $ids = $params['list'];
    $album_name = '';
  }

  // get pictures
  $query = '
SELECT *
  FROM ' . IMAGES_TABLE . '
  WHERE id IN (' . $ids . ')
  ORDER BY FIND_IN_SET(id, "' . $ids . '")
;';
  $pictures = hash_from_query($query, 'id');

  foreach ($pictures as $row) {
    // url
    if (!empty($params['album'])) {
      $url = make_picture_url(array(
        'image_id' => $row['id'],
        'category' => array(
          'id' => $params['album'],
          'name' => $album_name,
          'permalink' => '',
        )
      ));
    } else {
      $url = make_picture_url(array('image_id' => $row['id']));
    }

    $name = render_element_name($row);

    $tpl_vars['elements'][] = array(
      'ID' => $row['id'],
      'TN_ALT' => htmlspecialchars(strip_tags($name)),
      'NAME' => $name,
      'URL' => $url,
      'src_image' => new SrcImage($row),
    );
  }

  list($tpl_vars['img_size']['w'], $tpl_vars['img_size']['h']) =
    $enabled[$deriv_type]->sizing->ideal_size;

  $tpl_vars['id'] = crc32(uniqid($ids)); // need a unique id if we have multiple sliders
  $tpl_vars['derivative_params'] = ImageStdParams::get_by_type($deriv_type);

  if ($params['control_thumbs']) {
    $tpl_vars['derivative_params_thumb'] = ImageStdParams::get_custom(
      $params['thumbs_size'],
      $params['thumbs_size'],
      1,
      $params['thumbs_size'],
      $params['thumbs_size']
    );
  }

  $template->assign(array(
    'EXTENDED_DESC_PATH' => EXTENDED_DESC_PATH,
    'SLIDER' => $tpl_vars,
  ));

  $template->set_filename('extended_description_content', realpath(EXTENDED_DESC_PATH . 'template/slider.tpl'));
  return $template->parse('extended_description_content', true);
}

function extdesc_get_slick_slider($param)
{
  global $template, $conf, $page;
  $default_params = array(
    'album' =>     array('\d+', null),
    'nb_images' => array('\d+', 10),
    'random' =>    array('boolean', false),
    'list' =>      array('[\d,]+', null),
    'size' =>      array(extdesc_get_deriv_regex(), 'M'),
    'speed' =>     array('\d+', 5),
    'title' =>     array('boolean', false),
    'effect' =>    array('[a-zA-Z]+', 'fade'),
    'arrows' =>    array('boolean', true),
    'control' =>   array('yes|no|true|false|thumb', true),
    'elastic' =>   array('boolean', true),
    'thumbs_size' => array('\d+', 80),
  );

  $params = extdesc_parse_parameters($param, $default_params);

  // check size
  $deriv_type = extdesc_get_deriv_type($params['size']);
  $enabled = ImageStdParams::get_defined_type_map();
  if (empty($enabled[$deriv_type])) {
    return '(nivoSlider) size disabled';
  }

  // parameters
  if ($params['control'] === 'thumb') {
    $params['control'] = true;
    $params['control_thumbs'] = true;
  } else {
    $params['control'] = filter_var($params['control'], FILTER_VALIDATE_BOOLEAN);
    $params['control_thumbs'] = false;
  }

  $tpl_vars = $params;

  // pictures from album...
  if (!empty($params['album'])) {
    $query = '
SELECT name, image_order
  FROM ' . CATEGORIES_TABLE . '
  WHERE id = ' . $params['album'] . '
;';
    list($album_name, $order_by) = pwg_db_fetch_row(pwg_query($query));

    if ($params['random']) {
      $order_by = DB_RANDOM_FUNCTION . '()';
    } else if (empty($order_by)) {
      $order_by = str_replace('ORDER BY ', '', $conf['order_by_inside_category']);
    }

    // get pictures ids
    $query = '
SELECT image_id
  FROM ' . IMAGE_CATEGORY_TABLE . ' as ic
    INNER JOIN ' . IMAGES_TABLE . ' as i
    ON i.id = ic.image_id
  WHERE category_id = ' . $params['album'] . '
  ORDER BY ' . $order_by . '
  LIMIT ' . $params['nb_images'] . '
;';
    $ids = array_from_query($query, 'image_id');
    if (empty($ids)) {
      return '(nivoSlider) no photos in album #' . $params['album'];
    }
    $ids = implode(',', $ids);
  }
  // ...or pictures list
  else if (empty($params['list'])) {
    return '(nivoSlider) missing album id or photos list';
  } else {
    $ids = $params['list'];
    $album_name = '';
  }

  // get pictures
  $query = '
SELECT *
  FROM ' . IMAGES_TABLE . '
  WHERE id IN (' . $ids . ')
  ORDER BY FIND_IN_SET(id, "' . $ids . '")
;';
  $pictures = hash_from_query($query, 'id');

  foreach ($pictures as $row) {
    // url
    if (!empty($params['album'])) {
      $url = make_picture_url(array(
        'image_id' => $row['id'],
        'category' => array(
          'id' => $params['album'],
          'name' => $album_name,
          'permalink' => '',
        )
      ));
    } else {
      $url = make_picture_url(array('image_id' => $row['id']));
    }

    $name = render_element_name($row);

    $tpl_vars['elements'][] = array(
      'ID' => $row['id'],
      'TN_ALT' => htmlspecialchars(strip_tags($name)),
      'NAME' => $name,
      'URL' => $url,
      'src_image' => new SrcImage($row),
    );
  }

  list($tpl_vars['img_size']['w'], $tpl_vars['img_size']['h']) =
    $enabled[$deriv_type]->sizing->ideal_size;

  $tpl_vars['id'] = crc32(uniqid($ids)); // need a unique id if we have multiple sliders
  $tpl_vars['derivative_params'] = ImageStdParams::get_by_type($deriv_type);

  if ($params['control_thumbs']) {
    $tpl_vars['derivative_params_thumb'] = ImageStdParams::get_custom(
      $params['thumbs_size'],
      $params['thumbs_size'],
      1,
      $params['thumbs_size'],
      $params['thumbs_size']
    );
  }
  // $category_name = $page['category']['name'];
  $template->assign(array(
    'EXTENDED_DESC_PATH' => EXTENDED_DESC_PATH,
    'SLIDER' => $tpl_vars,
  ));

  $template->set_filename('slick_slider_template', EXTENDED_DESC_PATH . 'template/slick_slider.tpl');
  return $template->parse('slick_slider_template', true);
}


function extdesc_get_news_slider($param)
{
  global $template, $conf, $page;
  $default_params = array(
    'album' =>     array('\d+', null),
    'nb_images' => array('\d+', 10),
    'random' =>    array('boolean', false),
    'list' =>      array('[\d,]+', null),
    'size' =>      array(extdesc_get_deriv_regex(), 'M'),
    'speed' =>     array('\d+', 5),
    'title' =>     array('boolean', false),
    'effect' =>    array('[a-zA-Z]+', 'fade'),
    'arrows' =>    array('boolean', false),
    'control' =>   array('yes|no|true|false|thumb', false),
    'elastic' =>   array('boolean', false),
    'thumbs_size' => array('\d+', 80),
    'items' => array('\d+', null),
    'itemsDesktop' => array('[\d,]+', null),
    'itemsDesktopSmall' => array('[\d,]+', null),
    'itemsMobile' => array('[\d,]+', null),
  );

  $params = extdesc_parse_parameters($param, $default_params);

  // check size
  $deriv_type = extdesc_get_deriv_type($params['size']);
  $enabled = ImageStdParams::get_defined_type_map();
  if (empty($enabled[$deriv_type])) {
    return '(nivoSlider) size disabled';
  }

  // parameters
  if ($params['control'] === 'thumb') {
    $params['control'] = true;
    $params['control_thumbs'] = true;
  } else {
    $params['control'] = filter_var($params['control'], FILTER_VALIDATE_BOOLEAN);
    $params['control_thumbs'] = false;
  }

  $tpl_vars = $params;

  // pictures from album...
  if (!empty($params['album'])) {
    $query = '
SELECT name, image_order
  FROM ' . CATEGORIES_TABLE . '
  WHERE id = ' . $params['album'] . '
;';
    list($album_name, $order_by) = pwg_db_fetch_row(pwg_query($query));

    if ($params['random']) {
      $order_by = DB_RANDOM_FUNCTION . '()';
    } else if (empty($order_by)) {
      $order_by = str_replace('ORDER BY ', '', $conf['order_by_inside_category']);
    }

    // get pictures ids
    $query = '
SELECT image_id
  FROM ' . IMAGE_CATEGORY_TABLE . ' as ic
    INNER JOIN ' . IMAGES_TABLE . ' as i
    ON i.id = ic.image_id
  WHERE category_id = ' . $params['album'] . '
  ORDER BY ' . $order_by . '
  LIMIT ' . $params['nb_images'] . '
;';
    $ids = array_from_query($query, 'image_id');
    if (empty($ids)) {
      return '(nivoSlider) no photos in album #' . $params['album'];
    }
    $ids = implode(',', $ids);
  }
  // ...or pictures list
  else if (empty($params['list'])) {
    return '(nivoSlider) missing album id or photos list';
  } else {
    $ids = $params['list'];
    $album_name = '';
  }

  // get pictures
  $query = '
SELECT *
  FROM ' . IMAGES_TABLE . '
  WHERE id IN (' . $ids . ')
  ORDER BY FIND_IN_SET(id, "' . $ids . '")
;';
  $pictures = hash_from_query($query, 'id');
  // echo "<pre>";
  // var_dump($pictures);
  // echo "</pre>";
  foreach ($pictures as $row) {
    // url
    if (!empty($params['album'])) {
      $url = make_picture_url(array(
        'image_id' => $row['id'],
        'category' => array(
          'id' => $params['album'],
          'name' => $album_name,
          'permalink' => '',
        )
      ));
    } else {
      $url = make_picture_url(array('image_id' => $row['id']));
    }

    $name = render_element_name($row);
    $comment = html_entity_decode(strip_tags($row['comment'] ?? ''));
    if (!empty($comment)) {
      $comment = substr($comment, 0, 246) . "...";
    }
    $tpl_vars['elements'][] = array(
      'ID' => $row['id'],
      'TN_ALT' => htmlspecialchars(strip_tags($name)),
      'NAME' => $name,
      'URL' => $url,
      'src_image' => new SrcImage($row),
      'comment' => $comment,
      'date_available' => date('M d, Y', strtotime($row['date_available']))
    );
  }

  list($tpl_vars['img_size']['w'], $tpl_vars['img_size']['h']) =
    $enabled[$deriv_type]->sizing->ideal_size;

  $tpl_vars['id'] = crc32(uniqid($ids)); // need a unique id if we have multiple sliders
  $tpl_vars['derivative_params'] = ImageStdParams::get_by_type($deriv_type);

  if ($params['control_thumbs']) {
    $tpl_vars['derivative_params_thumb'] = ImageStdParams::get_custom(
      $params['thumbs_size'],
      $params['thumbs_size'],
      1,
      $params['thumbs_size'],
      $params['thumbs_size']
    );
  }
  // $category_name = $page['category']['name'];
  $template->assign(array(
    'EXTENDED_DESC_PATH' => EXTENDED_DESC_PATH,
    'SLIDER' => $tpl_vars,
  ));

  $template->set_filename('news_slider_template', EXTENDED_DESC_PATH . 'template/news_slider.tpl');
  return $template->parse('news_slider_template', true);
}


/**
 * Return html code for login link
 *
 * @bool   html: return complete html structure (default: true)
 * @string text: link text, translatable        (default: Login)
 */
function extdesc_get_login_link($param)
{
  $default_params = array(
    'html' => array('boolean', true),
    'text' => array('".*"', ''),
  );

  $params = extdesc_parse_parameters($param, $default_params);

  $url =
    get_root_url() . 'identification.php?redirect='
    . urlencode(urlencode($_SERVER['REQUEST_URI']))
    . '&amp;hide_redirect_error=1';

  if ($params['html']) {
    if (empty($params['text'])) {
      $params['text'] = l10n('Login');
    } else {
      $params['text'] = get_user_language_desc(mb_substr($params['text'], 1, -1));
    }

    return '<a href="' . $url . '">' . $params['text'] . '</a>';
  } else {
    return $url;
  }
}

/**
 * Parse tags parameters
 */
function extdesc_parse_parameters($param, $default_params)
{
  if (is_array($param)) {
    return $param;
  }

  $params = array();

  foreach ($default_params as $name => $value) {
    $is_bool = false;
    if ($value[0] == 'boolean') {
      $is_bool = true;
      $value[0] = 'yes|no|true|false';
    }

    if (preg_match('#' . $name . '=(' . $value[0] . ')#i', $param, $matches)) {
      $params[$name] = $matches[1];
      if ($is_bool) {
        $params[$name] = filter_var($params[$name], FILTER_VALIDATE_BOOLEAN);
      }
    } else {
      $params[$name] = $value[1];
    }
  }

  return $params;
}

/**
 * Translates shorthand sizes to internal names
 */
function extdesc_get_deriv_type($size)
{
  $size = strtoupper($size);

  $size_map = array(
    'SQ' => IMG_SQUARE,
    'square' => IMG_SQUARE,
    'TH' => IMG_THUMB,
    'thumbnail' => IMG_THUMB,
    'XXS' => IMG_XXSMALL,
    'xxsmall' => IMG_XXSMALL,
    'XS' => IMG_XSMALL,
    'xsmall' => IMG_XSMALL,
    'S' => IMG_SMALL,
    'small' => IMG_SMALL,
    'M' => IMG_MEDIUM,
    'medium' => IMG_MEDIUM,
    'L' => IMG_LARGE,
    'large' => IMG_LARGE,
    'XL' => IMG_XLARGE,
    'xlarge' => IMG_XLARGE,
    'XXL' => IMG_XXLARGE,
    'xxlarge' => IMG_XXLARGE,
  );

  if (!array_key_exists($size, $size_map)) {
    $size = 'M';
  }

  return $size_map[$size];
}

function extdesc_get_deriv_regex()
{
  return join('|', array(
    'SQ',
    'square',
    'TH',
    'thumbnail',
    'XXS',
    'xxsmall',
    'XS',
    'xsmall',
    'S',
    'small',
    'M',
    'medium',
    'L',
    'large',
    'XL',
    'xlarge',
    'XXL',
    'xxlarge'
  ));
}

function save_to_cache($key, $data)
{
  // Đường dẫn thư mục cache
  $cache_dir = PHPWG_ROOT_PATH . '_data/tmp/';
  // Tạo tên file cache dựa trên $key
  $cache_file = $cache_dir . 'cache_categories_' . md5($key) . '.json';

  // Kiểm tra xem thư mục cache có tồn tại không, nếu không thì tạo mới
  if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
  }

  // Nếu file cache đã tồn tại, kiểm tra nội dung
  if (file_exists($cache_file)) {
    $current_data = json_decode(file_get_contents($cache_file), true);

    // Nếu dữ liệu mới giống với dữ liệu hiện tại, không cần ghi đè
    if ($current_data === $data) {
      return;
    }
  }

  // Chuyển đổi dữ liệu sang JSON và lưu vào file
  file_put_contents($cache_file, json_encode($data, JSON_PRETTY_PRINT));
}


function load_from_cache($key)
{
  // Đường dẫn thư mục cache
  $cache_dir = PHPWG_ROOT_PATH . '_data/tmp/';
  // Tên file cache dựa trên $key
  $cache_file = $cache_dir . 'cache_categories_' . md5($key) . '.json';

  // Kiểm tra nếu file tồn tại và không hết hạn
  if (file_exists($cache_file)) {
    $data = json_decode(file_get_contents($cache_file), true);
    return $data;
  }

  // Trả về null nếu không tìm thấy dữ liệu trong cache
  return null;
}
