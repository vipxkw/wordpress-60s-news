<?php
function getRandomFileName($directory)
{
    $mydir = dir($directory);
    $files = array();
    while($file = $mydir->read())
    {
        if(is_dir("$directory/$file")) continue;
        if($file == ".")  continue;
        if($file == "..") continue;

        array_push($files, $file);
    }
    $mydir->close();

    srand((float) microtime() * 10000000);
    $index = array_rand($files);
    return $files[$index];
}

$all_data = file_get_contents("https://api.chinasclm.com/api/api-60s-news");
$all_data = json_decode($all_data);

// 检查API响应是否成功
if ($all_data->code != 200) {
    die('API请求失败: ' . $all_data->msg);
}

$title = $all_data->title;  

// 构建新闻内容：将数组元素拼接为HTML段落
$content = '';
foreach ($all_data->news as $news_item) {
    $content .= '<p>' . $news_item . '</p>';
}

// 转换为时间戳
$update_time = strtotime($all_data->update_time);

// 随机选择图片
$number = date('w', $update_time); 
$rand_img = getRandomFileName("60s/".$number);
$rand_path = $number. "/" . $rand_img;

// 添加头图到内容开头
$content = '<img class="size-full wp-image-156 aligncenter" src="/60s/'.$rand_path.'" alt="" width="720" height="350" />'.$content;

require __DIR__ . '/wp-config.php';
global $wpdb;
date_default_timezone_set('PRC');
$post_tag_arr = array();

// 先检查文章分类是否存在
$term_taxonomy_id = $wpdb->get_row("SELECT tt.term_taxonomy_id from $wpdb->terms t join $wpdb->term_taxonomy tt on t.term_id = tt.term_id where t.name = '每日新闻' and tt.taxonomy = 'category' ")->term_taxonomy_id;
if (!$term_taxonomy_id) {
    $wpdb->query("insert into $wpdb->terms (name,slug,term_group)VALUES('每日新闻','news','0')");
    $category_id = $wpdb->insert_id;
    $wpdb->query("insert into $wpdb->term_taxonomy (term_id,taxonomy,description,parent,count)VALUES($category_id,'category','','0','1')");
    $term_taxonomy_id = $wpdb->insert_id;
}
$post_tag_arr[] = $term_taxonomy_id;

$html = $content;

// 标题存在则不插入
$posts = $wpdb->get_row("SELECT id from $wpdb->posts where post_title = '$title' ");
if (!$posts) {
    $now = current_time('mysql');
    $now_gmt = current_time('mysql', 1);
    $wpdb->insert(
        $wpdb->posts,
        array(
            'post_author' => 2,
            'post_date' => $now,
            'post_date_gmt' => $now_gmt,
            'post_content' => $html,
            'post_title' => $title,
            'post_excerpt' => '',
            'post_status' => 'publish',
            'comment_status' => 'open',
            'ping_status' => 'open',
            'post_password' => '',
            'post_name' => $title,
            'to_ping' => '',
            'pinged' => '',
            'post_modified' => $now,
            'post_modified_gmt' => $now_gmt,
            'post_content_filtered' => '',
            'post_parent' => '0',
            'guid' => '',//文章链接 插入后修改
            'menu_order' => '0',
            'post_type' => 'post',
            'post_mime_type' => '',
            'comment_count' => '0',

        )
    );
    $insertid = $wpdb->insert_id;
    $post_guid = get_option('home') . '/?p=' . $insertid;
    $wpdb->query(" UPDATE $wpdb->posts SET guid='$post_guid' where id = $insertid ");

    //插入文章和分类、标签、专题的关系
    $sql = " INSERT INTO $wpdb->term_relationships (object_id,term_taxonomy_id,term_order) VALUES ";
    foreach ($post_tag_arr as $key => $value) {
        $sql .= "($insertid, $value, '0'),";
    }
    $wpdb->query(rtrim($sql, ","));
  echo date('Y-m-d H:i:s')."-文章插入成功\n";
}else {
  echo date('Y-m-d H:i:s')."-文章已存在\n";
}
