<?php
/**
 * 易经六十四卦象分析报告系统 - 修复定位和天气数据错误版本
 * 核心修复：定位改用高德API，天气改用高德API，坐标系适配，增强错误处理
 * 新增功能：密码全流程统一管理，PHPMailer自动发送密码
 */
// 1. 把命名空间移到文件最顶部（<?php 之后，所有代码之前）
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// 解决报告时间不准确：设置中国时区
date_default_timezone_set('PRC');
// 1. 开启输出缓冲（解决TCPDF输出错误的核心：清空所有提前输出的内容）
ob_start();
// 2. 开启SESSION（必须在最顶部，无任何输出之前）
session_start();

// ------------- 配置项（替换为自己的高德API Key和邮件配置）-------------
// 高德开发者API Key（需自行申请：https://lbs.amap.com/）
define('AMAP_KEY', '5aebc2974dbed9a78e9dd4bb28a8cd9a'); // 替换为自己的高德Key
define('DEFAULT_LAT', '39.9042'); // 北京纬度
define('DEFAULT_LON', '116.4074'); // 北京经度
define('IP_API_URL', 'http://ip-api.com/json/'); // 备用IP定位服务

// 邮件配置
define('MAIL_TO', 'good_job_001@163.com');
define('MAIL_HOST', 'smtp.163.com'); // 邮件服务器地址
define('MAIL_PORT', 465); // 邮件服务器端口
define('MAIL_USER', 'good_job_001@163.com'); // 发件人邮箱
define('MAIL_PASS', 'XDfSQ3AQkHdi3vQR'); // 发件人密码/授权码
define('MAIL_FROM', '易经报告系统'); // 发件人名称

// 系统配置
define('PASSWORD_LENGTH', 8); // 密码长度
define('PASSWORD_LOG_FILE', __DIR__ . '/password_log.txt');
define('REPORT_DIR', __DIR__ . '/reports/');
// MySQL数据库配置
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PWD', 'whyylw_666666');
define('DB_NAME', 'yijing_data');

// 检查PHPMailer是否存在，不存在则提示
if (!file_exists(__DIR__ . '/PHPMailer/src/PHPMailer.php')) {
    die('请先下载PHPMailer并放置在PHPMailer目录下（https://github.com/PHPMailer/PHPMailer）');
}
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';

// ------------- 检查并创建报告目录 -------------
if (!is_dir(REPORT_DIR)) {
    mkdir(REPORT_DIR, 0755, true); // 递归创建目录
}

// ------------- 全局默认配置（兜底）-------------
// 默认解读数组
$DEFAULT_INTERPRETATION = [
    'fortune' => '暂无解读',
    'wealth' => '暂无解读',
    'career' => '暂无解读',
    'marriage' => '暂无解读',
    'health' => '暂无解读',
    'lifespan' => '暂无解读'
];
// 默认卦象信息数组
$DEFAULT_HEX_INFO = [
    'chinese_name' => '未知卦',
    'english_name' => 'Unknown',
    'image' => '暂无卦辞',
    'judgment' => '暂无彖辞',
    'image_comment' => '暂无象辞',
    'hexagram' => '☰|☷',
    'lines' => [],
    'interpretation' => $DEFAULT_INTERPRETATION,
    'explain' => '暂无卦解',
    'good_bad' => '平'
];
// 默认解读键名映射
$DEFAULT_KEY_MAP = [
    'fortune' => '时运',
    'wealth' => '财富',
    'career' => '事业',
    'marriage' => '婚姻',
    'health' => '健康',
    'lifespan' => '寿命'
];

// ------------- 违禁词列表（可根据需求扩展）-------------
$forbidden_words = [
    '暴力', '色情', '赌博', '吸毒', '自杀', '杀人', '抢劫', '盗窃', '诈骗',
    '反动', '邪教', '辱骂', '诅咒', '恶意', '仇恨', '极端', '违法', '违规'
];

// ------------- 密码处理核心函数 -------------
/**
 * 生成随机密码
 * @return string 生成的密码
 */
function generate_password() {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $password = '';
    for ($i = 0; $i < PASSWORD_LENGTH; $i++) {
        $password .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * 记录密码日志
 * @param string $password 密码
 * @param string $reportId 报告ID
 * @return bool 是否成功
 */
function log_password($password, $reportId) {
    $logEntry = date('Y-m-d H:i:s') . " - 报告ID: {$reportId} - 密码: {$password}\n";
    return file_put_contents(PASSWORD_LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX) !== false;
}

/**
 * 使用PHPMailer发送密码邮件
 * @param string $password 密码
 * @param string $reportId 报告ID
 * @return array 发送结果
 */
// ------------邮件自动发送密码-----------------

/**
 * 修复后的发送密码邮件函数
 * @param string $password 密码
 * @param string $reportId 报告ID
 * @param int $hexagram_number 卦数（新增，解决邮件内容变量问题）
 * @param string $main_inquiry 主询内容（新增，解决邮件内容变量问题）
 * @return array 发送结果
 */

 
function send_password_email($password, $reportId, $hexagram_number = 0, $main_inquiry = '') {
    $result = ['success' => false, 'error' => ''];

    // 检查配置常量
    if (!defined('MAIL_TO') || !defined('MAIL_HOST') || !defined('MAIL_USER') || !defined('MAIL_PASS') || !defined('MAIL_PORT') || !defined('MAIL_FROM')) {
        $result['error'] = '邮件配置常量未定义';
        return $result;
    }

    // 定义PHPMailer的绝对路径（强制使用__DIR__确保路径正确）
    $phpMailerSrc = __DIR__ . '/PHPMailer/src/';
    $files = [
        $phpMailerSrc . 'Exception.php',
        $phpMailerSrc . 'SMTP.php',
        $phpMailerSrc . 'PHPMailer.php'
    ];

    // 强制引入所有核心文件（按顺序，先Exception，再SMTP，最后PHPMailer）
    foreach ($files as $file) {
        if (!file_exists($file)) {
            $result['error'] = 'PHPMailer文件缺失：' . $file;
            return $result;
        }
        // 用require_once防止重复引入，同时确保文件被加载
        require_once $file;
    }

    try {
        // 注意：如果命名空间仍有问题，可直接使用完整命名空间实例化
        $mail = new PHPMailer(true); // 或：new \PHPMailer\PHPMailer\PHPMailer(true);

        // SMTP配置
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USER;
        $mail->Password = MAIL_PASS;
        // 兼容5.x/6.x的加密方式（彻底解决常量问题）
        if (defined('\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SSL')) {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SSL;
        } else {
            $mail->SMTPSecure = 'ssl';
        }
        $mail->Port = MAIL_PORT;

        // 关闭SMTP调试（可选，开启可看调试信息：0=关闭，1=客户端，2=服务器+客户端）
        $mail->SMTPDebug = 0;
        // 强制使用SMTP类（解决SMTP实例化失败）
        $mail->Debugoutput = 'html';

        // 发件人/收件人
        $mail->setFrom(MAIL_USER, MAIL_FROM);
        $mail->addAddress(MAIL_TO); // 收件人

        // 邮件内容
        $mail->Subject = '易经报告下载密码';
        $mail->Body = "卦数：{$hexagram_number}，报告ID：{$reportId}，密码：{$password}，主询：{$main_inquiry}";
        $mail->AltBody = strip_tags($mail->Body); // 纯文本内容

        // 发送
        $mail->send();
        $result['success'] = true;
        $result['error'] = '';
    } catch (Exception $e) { // 或：catch (\PHPMailer\PHPMailer\Exception $e)
        $result['error'] = '邮件发送失败：' . $e->getMessage() . '（错误信息：' . (isset($mail) ? $mail->ErrorInfo : '未知') . '）';
        error_log($result['error']);
    } catch (\Exception $e) { // 捕获全局异常
        $result['error'] = '系统异常：' . $e->getMessage();
        error_log($result['error']);
    }

    return $result;
}




// ------------- AI生成卦解函数（核心新增）-------------
function ai_generate_hexagram_explanation($hexagram_number, $hexagram_data, $hexagram_meanings) {
    $hex_name = $hexagram_data['chinese_name'] ?? '未知卦象';
    $location = $hexagram_data['location'] ?? '未知地点';
    $main_inquiry = $hexagram_data['main_inquiry'] ?? '无';
    $core_meaning = $hexagram_meanings[$hexagram_number] ?? $hexagram_meanings[1];

    $explanation_templates = [
        "{$hex_name}（第{$hexagram_number}卦），{$location}之时，{$core_meaning}。针对您关注的「{$main_inquiry}」，此卦提示您需遵循卦象核心寓意，凡事循序渐进，不可冒进，静待时机则水到渠成。",
        "{$hex_name}卦（第{$hexagram_number}卦），{$location}的{$main_inquiry}方面将有卦象所预示的发展趋势。{$core_meaning}，您需把握卦象中的关键指引，坚守正道，灵活调整策略以应对变化。",
        "第{$hexagram_number}卦{$hex_name}，{$location}的{$main_inquiry}运势契合卦象核心：{$core_meaning}。此卦象征当前阶段的发展规律，您需顺应卦象指引，在{$main_inquiry}上注重细节把控与时机选择，自然能趋吉避凶。",
        "{$hex_name}卦解（第{$hexagram_number}卦）：{$core_meaning}。结合{$location}与您的{$main_inquiry}需求，此卦提示您需以卦象寓意为准则，在行动中保持理性，在决策中坚守本心，方能契合卦象的正向指引。"
    ];
    $ai_explanation = $explanation_templates[array_rand($explanation_templates)];

    return trim($ai_explanation);
}

// ------------- 工具函数 -------------
function get_nested_value($array, $keys, $default = '未知') {
    $current = $array;
    foreach ($keys as $key) {
        if (!is_array($current) || !isset($current[$key])) {
            return $default;
        }
        $current = $current[$key];
    }
    return $current === null ? $default : $current;
}

function process_main_inquiry($inquiry, $forbidden_words) {
    $filtered = preg_replace('/[^\x{4e00}-\x{9fa5}\s]/u', '', $inquiry);
    foreach ($forbidden_words as $word) {
        $filtered = str_replace($word, '', $filtered);
    }
    return trim(preg_replace('/\s+/', ' ', $filtered));
}

/**
 * 修复：优化真实IP获取逻辑，排除内网IP干扰
 */
function get_real_ip() {
    $ip = '127.0.0.1';
    $ip_headers = [
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP',
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];

    foreach ($ip_headers as $header) {
        if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
            $ip_list = explode(',', $_SERVER[$header]);
            $temp_ip = trim($ip_list[0]);
            // 排除内网IP，只保留公网IP
            if (filter_var($temp_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $ip = $temp_ip;
                break;
            }
        }
    }

    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = '127.0.0.1';
    }
    return $ip;
}
/**
 * 新增：百度BD09LL坐标系转高德GCJ02坐标系（适配国内天气接口）
 * @param float $bd_lat 百度纬度
 * @param float $bd_lon 百度经度
 * @return array [lat, lon]
 */
function bd09ll_to_gcj02($bd_lat, $bd_lon) {
    $x_pi = 3.14159265358979324 * 3000.0 / 180.0;
    $x = $bd_lon - 0.0065;
    $y = $bd_lat - 0.006;
    $z = sqrt($x * $x + $y * $y) - 0.00002 * sin($y * $x_pi);
    $theta = atan2($y, $x) - 0.000003 * cos($x * $x_pi);
    $gcj_lon = $z * cos($theta);
    $gcj_lat = $z * sin($theta);
    return [$gcj_lat, $gcj_lon];
}

/**
 * 修复：获取IP对应的地理位置和天气信息（改用高德API）
 * @param string $ip 用户IP
 * @return array
 */
function get_ip_weather_info($ip) {
    // 初始化默认值（北京，确保基础地址不为空）
    $default_data = [
        'ip' => $ip,
        'lat' => DEFAULT_LAT,
        'lon' => DEFAULT_LON,
        'address' => '中国 北京市', // 默认显示中国+北京
        'temp' => '20',
        'weather' => '多云',
        'wind' => '北风 2级',
        'current_weather' => '多云，气温：20℃，北风2级',
        'debug' => '使用默认数据'
    ];

    // ------------- 步骤1：高德IP定位获取地理位置和经纬度（优先）-------------
    $amap_success = false;
    try {
        $amap_url = "https://restapi.amap.com/v3/ip?ip={$ip}&key=" . AMAP_KEY;
        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'header' => ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36']
            ]
        ]);
        $amap_res = json_decode(@file_get_contents($amap_url, false, $context), true);
        $default_data['debug'] = '高德定位返回：' . json_encode($amap_res);

        if (isset($amap_res['status']) && $amap_res['status'] == '1') {
            // ================ 强化：容错处理所有省市区字段，确保非空 ================
            // 1. 省份：API返回空则用“北京市”兜底
            $province = isset($amap_res['province']) ? $amap_res['province'] : '';
            $province = is_array($province) ? reset($province) : (string)$province;
            $province = $province ?: '北京市'; // 空值则用默认

            // 2. 城市：API返回空则用省份兜底（如省份是北京市，城市也为北京市）
            $city = isset($amap_res['city']) ? $amap_res['city'] : '';
            $city = is_array($city) ? reset($city) : (string)$city;
            $city = $city ?: $province; // 空值则继承省份

            // 3. 区县：API返回空则留空（可选：也可设默认，如“朝阳区”）
            $district = isset($amap_res['district']) ? $amap_res['district'] : '';
            $district = is_array($district) ? reset($district) : (string)$district;

            // 4. 经纬度：API返回空则用默认
            $lat = isset($amap_res['lat']) ? $amap_res['lat'] : DEFAULT_LAT;
            $lat = is_array($lat) ? reset($lat) : (string)$lat;
            $lng = isset($amap_res['lng']) ? $amap_res['lng'] : DEFAULT_LON;
            $lng = is_array($lng) ? reset($lng) : (string)$lng;

            // 5. 行政区划代码：空则用北京110000
            $adcode = isset($amap_res['adcode']) ? $amap_res['adcode'] : '';
            $adcode = is_array($adcode) ? reset($adcode) : (string)$adcode;
            $adcode = $adcode ?: '110000';

            // ================ 优化：地址拼接，确保至少显示省和市 ================
            $address_parts = ["中国", $province, $city];
            if (!empty($district)) {
                $address_parts[] = $district; // 区县非空才添加
            }
            $address = implode(' ', $address_parts); // 用空格拼接，避免多余符号
            $address = trim(preg_replace('/\s+/', ' ', $address)); // 去除多余空格

            // 更新默认数据
            $default_data['address'] = $address;
            $default_data['lat'] = $lat;
            $default_data['lon'] = $lng;
            $amap_success = true;

            // ------------- 步骤2：高德天气API获取实时天气 -------------
            $weather_url = "https://restapi.amap.com/v3/weather/weatherInfo?city={$adcode}&key=" . AMAP_KEY . "&extensions=base";
            $weather_res = json_decode(@file_get_contents($weather_url, false, $context), true);
            $default_data['debug'] .= ' | 高德天气返回：' . json_encode($weather_res);

            if (isset($weather_res['status']) && $weather_res['status'] == '1' && !empty($weather_res['lives'])) {
                $live_weather = $weather_res['lives'][0];
                $default_data['temp'] = $live_weather['temperature'] ?? $default_data['temp'];
                $default_data['weather'] = $live_weather['weather'] ?? $default_data['weather'];
                $default_data['wind'] = $live_weather['winddirection'] . ' ' . $live_weather['windpower'] . '级';
                $default_data['current_weather'] = "{$default_data['weather']}，气温：{$default_data['temp']}℃，{$default_data['wind']}";
            }
        }
    } catch (Exception $e) {
        $default_data['debug'] .= ' | 高德接口异常：' . $e->getMessage();
    }

    // ------------- 步骤3：高德定位失败时，使用ip-api.com作为备用 -------------
    if (!$amap_success) {
        try {
            $ip_api_url = IP_API_URL . $ip . "?lang=zh-CN";
            $context = stream_context_create(['http' => ['timeout' => 5]]);
            $ip_api_res = json_decode(@file_get_contents($ip_api_url, false, $context), true);
            $default_data['debug'] .= ' | ip-api返回：' . json_encode($ip_api_res);

            if (isset($ip_api_res['status']) && $ip_api_res['status'] == 'success') {
                // 处理ip-api返回的字段，确保非空
                $country = $ip_api_res['country'] ?? '中国';
                $country = is_array($country) ? reset($country) : (string)$country;
                $region = $ip_api_res['regionName'] ?? '北京市';
                $region = is_array($region) ? reset($region) : (string)$region;
                $city = $ip_api_res['city'] ?? '北京市';
                $city = is_array($city) ? reset($city) : (string)$city;

                // 拼接地址
                $address_parts = [$country, $region, $city];
                $address = implode(' ', $address_parts);
                $address = trim(preg_replace('/\s+/', ' ', $address));

                // 更新数据
                $default_data['address'] = $address;
                $default_data['lat'] = $ip_api_res['lat'] ?? DEFAULT_LAT;
                $default_data['lon'] = $ip_api_res['lon'] ?? DEFAULT_LON;

                // 备用天气：使用经纬度调用高德天气
                try {
                    $weather_url = "https://restapi.amap.com/v3/weather/weatherInfo?location={$default_data['lon']},{$default_data['lat']}&key=" . AMAP_KEY . "&extensions=base";
                    $weather_res = json_decode(@file_get_contents($weather_url, false, $context), true);
                    if (isset($weather_res['status']) && $weather_res['status'] == '1' && !empty($weather_res['lives'])) {
                        $live_weather = $weather_res['lives'][0];
                        $default_data['temp'] = $live_weather['temperature'] ?? $default_data['temp'];
                        $default_data['weather'] = $live_weather['weather'] ?? $default_data['weather'];
                        $default_data['wind'] = $live_weather['winddirection'] . ' ' . $live_weather['windpower'] . '级';
                        $default_data['current_weather'] = "{$default_data['weather']}，气温：{$default_data['temp']}℃，{$default_data['wind']}";
                    }
                } catch (Exception $e) {
                    $default_data['debug'] .= ' | 备用天气接口异常：' . $e->getMessage();
                }
            }
        } catch (Exception $e) {
            $default_data['debug'] .= ' | ip-api接口异常：' . $e->getMessage();
        }
    }

    return $default_data;
}

function connect_db() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PWD, DB_NAME);
    if ($conn->connect_error) {
        error_log("数据库连接失败：" . $conn->connect_error);
        return null;
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

function _report_data($data) {
    $conn = connect_db();
    if (!$conn) {
        return false;
    }

    $fields = 'doc_password, report_time, user_ip, location, current_weather, main_inquiry, core_keyword, hex_order, hex_name, hex_symbol, hex_canticle, tuan_canticle, xiang_canticle, yao_canticle, hex_explain, good_bad, analysis_fortune, analysis_wealth, analysis_career, analysis_marriage, analysis_health, analysis_lifespan, suggestion1, suggestion2, suggestion3, create_time';
    $placeholders = '?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?';
    $sql = "INSERT INTO yijing_report ($fields) VALUES ($placeholders)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("预处理失败: " . $conn->error);
        $conn->close();
        return false;
    }

    $bind_vars = [
        $data['doc_password'] ?? '',
        $data['report_time'] ?? date('Y-m-d H:i:s'),
        $data['user_ip'] ?? '',
        $data['location'] ?? '',
        $data['current_weather'] ?? '',
        $data['main_inquiry'] ?? '',
        $data['core_keyword'] ?? '',
        $data['hex_order'] ?? 0,
        $data['hex_name'] ?? '',
        $data['hex_symbol'] ?? '',
        $data['hex_canticle'] ?? '',
        $data['tuan_canticle'] ?? '',
        $data['xiang_canticle'] ?? '',
        $data['yao_canticle'] ?? '',
        $data['hex_explain'] ?? '',
        $data['good_bad'] ?? '',
        $data['analysis_fortune'] ?? '',
        $data['analysis_wealth'] ?? '',
        $data['analysis_career'] ?? '',
        $data['analysis_marriage'] ?? '',
        $data['analysis_health'] ?? '',
        $data['analysis_lifespan'] ?? '',
        $data['suggestion1'] ?? '',
        $data['suggestion2'] ?? '',
        $data['suggestion3'] ?? '',
        $data['create_time'] ?? date('Y-m-d H:i:s')
    ];

    $type_str = "ssssssssssssssssssssssssss";

    try {
        $stmt->bind_param($type_str, ...$bind_vars);
    } catch (Error $e) {
        $params = array_merge([$type_str], $bind_vars);
        foreach ($params as $key => &$val) {}
        call_user_func_array([$stmt, 'bind_param'], $params);
    }

    $result = $stmt->execute();
    if (!$result) {
        error_log("数据插入失败：" . $stmt->error);
    }

    // 获取插入的报告ID
    $reportId = $conn->insert_id;
    
    $stmt->close();
    $conn->close();

    // 找到原调用代码的位置，替换为：
if (!empty($data['doc_password']) && $reportId) {
    log_password($data['doc_password'], $reportId);
    // 传递完整参数：密码、报告ID、卦数、主询内容
    $mailResult = send_password_email(
        $data['doc_password'],
        $reportId,
        $data['hex_order'] ?? 0, // 卦数（从报告数据中获取）
        $data['main_inquiry'] ?? '' // 主询内容（从报告数据中获取）
    );
    // 检查邮件发送结果（可选，用于调试）
    if (!$mailResult['success']) {
        error_log("密码邮件发送失败: " . $mailResult['error']);
    }
}

}

// ------------- 密码验证函数 -------------
function verify_password($reportId, $password) {
    $conn = connect_db();
    if (!$conn) {
        return false;
    }

    $sql = "SELECT doc_password FROM yijing_report WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("密码验证预处理失败: " . $conn->error);
        $conn->close();
        return false;
    }

    $stmt->bind_param("i", $reportId);
    $stmt->execute();
    $stmt->bind_result($storedPassword);
    $stmt->fetch();
    
    $stmt->close();
    $conn->close();

    // 直接比较原始密码（保持与生成、存储、加密逻辑一致）
    return $storedPassword === $password;
}


// ------------- PDF生成加密函数（示例，需根据实际PDF库调整）-------------
function generate_encrypted_pdf($reportData, $password) {
    // 假设使用TCPDF或其他PDF库
    // $pdf = new TCPDF();
    // ...添加内容...
    // 设置PDF密码保护
    // $pdf->SetProtection(['print', 'copy'], $password);
    // $pdf->Output(REPORT_DIR . $reportData['id'] . '.pdf', 'F');
    return true;
}

// ------------- 64卦核心寓意（完整）-------------
$hexagram_meanings = [
    1 => '乾卦代表天，象征刚健、进取、自强不息、领导力、发展潜力，寓意凡事需积极主动，坚守正道，持续精进',
    2 => '坤卦代表地，象征包容、厚德载物、柔顺、务实、积累，寓意凡事需以柔克刚，踏实做事，包容他人',
    3 => '屯卦代表萌芽，象征初创、困难、积累，寓意凡事需耐心起步，克服初期阻碍，稳扎稳打',
    4 => '蒙卦代表启蒙，象征学习、教育、探索，寓意凡事需虚心求教，持续学习，突破认知局限',
    5 => '需卦代表等待，象征时机、耐心、准备，寓意凡事需静待时机，做好准备，不可急躁',
    6 => '讼卦代表争讼，象征矛盾、沟通、和解，寓意凡事需避免争执，以和为贵，主动沟通',
    7 => '师卦代表军队，象征团队、领导力、执行，寓意凡事需依靠团队，明确目标，果断执行',
    8 => '比卦代表亲近，象征合作、人脉、信任，寓意凡事需广结善缘，诚信待人，互利共赢',
    9 => '小畜卦代表小积蓄，象征点滴积累、谦逊守分、蓄势待发，寓意凡事需注重点滴积累，谨守分寸，静待时机成熟再行动',
    10 => '履卦代表行走，象征实践、礼义、谨慎，寓意凡事需脚踏实地，谨守礼仪规范，稳步前行不冒进',
    11 => '泰卦代表通泰，象征阴阳和畅、上下相通、顺遂，寓意凡事需顺应时势，开放包容，促成和谐共赢的局面',
    12 => '否卦代表闭塞，象征阴阳阻隔、上下隔阂、困境，寓意凡事需坚守正道，隐忍待时，静待否极泰来的转机',
    13 => '同人卦代表和同，象征团结、共识、协作，寓意凡事需求同存异，凝心聚力，与人为善促成集体目标',
    14 => '大有卦代表富有，象征丰盛、明德、守成，寓意凡事需心怀宽广，以德居位，珍惜所得并回馈他人与社会',
    15 => '谦卦代表谦逊，象征虚怀、礼让、德业，寓意凡事需保持谦卑之心，虚心待人接物，以谦德立身行事',
    16 => '豫卦代表愉悦，象征和顺、预备、乐群，寓意凡事需劳逸结合，乐而不淫，提前谋划以保长久安乐',
    17 => '随卦代表跟随，象征顺势、变通、择善，寓意凡事需审时度势，顺应事物规律，择善而从并灵活调整',
    18 => '蛊卦代表弊害，象征腐败、革新、除弊，寓意凡事需洞察潜在弊端，果断整治革新，正本清源恢复秩序',
    19 => '临卦代表亲临，象征领导、督导、亲民，寓意凡事需以身作则，深入基层，以仁政和智慧引领团队或局面',
    20 => '观卦代表观察，象征审视、学习、洞察，寓意凡事需高瞻远瞩，细致观察事物本质，三思而后行',
    21 => '噬嗑卦代表咬合，象征决断、惩戒、解决问题，寓意凡事需勇于直面矛盾，果断处理障碍，明辨是非',
    22 => '贲卦代表装饰，象征文饰、礼仪、外在与内在，寓意凡事需注重外在修养与内在德行的统一，文质彬彬',
    23 => '剥卦代表剥落，象征衰败、退守、隐忍，寓意凡事需认清形势，收敛锋芒，守正避祸，静待转机',
    24 => '复卦代表回归，象征复兴、改过、初心，寓意凡事需及时反思修正，回归正道，重拾初心再出发',
    25 => '无妄卦代表无妄，象征真诚、守正、不妄为，寓意凡事需心怀坦荡，依道而行，不可肆意妄为',
    26 => '大畜卦代表大积蓄，象征厚积薄发、德行积累、包容，寓意凡事需注重长远积累，蓄养德才，待时而动',
    27 => '颐卦代表颐养，象征养生、教化、滋养，寓意凡事需注重身心调养，以德育人，滋养万物而不索取',
    28 => '大过卦代表大过，象征过度、危机、革新，寓意凡事需警惕极端，勇于突破旧格局，以刚柔并济化解危机',
    29 => '坎卦代表水，象征险陷、智慧、坚韧，寓意凡事需直面艰险，以智慧和毅力突破困境，越挫越勇',
    30 => '离卦代表火，象征光明、依附、热情，寓意凡事需保持光明磊落，择善而从，以热情和真诚凝聚力量',
    31 => '咸卦代表感应，象征情感、沟通、相亲，寓意凡事需以心交心，感同身受，建立真诚的情感连接',
    32 => '恒卦代表恒常，象征坚持、长久、恒心，寓意凡事需坚守初心，持之以恒，以恒心成就事业与情感',
    33 => '遁卦代表退隐，象征避世、隐忍、保全，寓意凡事需审时度势，适时退避，以退为进保全自身',
    34 => '大壮卦代表大壮，象征强盛、刚健、节制，寓意凡事需乘势而上，但不可恃强凌弱，保持节制与谦逊',
    35 => '晋卦代表晋升，象征上进、光明、发展，寓意凡事需积极进取，顺势而为，以德行赢得认可与提升',
    36 => '明夷卦代表明夷，象征光明受损、隐忍、守正，寓意凡事需在困境中坚守正道，韬光养晦等待时机',
    37 => '家人卦代表家庭，象征和睦、家教、责任，寓意凡事需注重家庭和谐，以良好的家风立身行事',
    38 => '睽卦代表乖离，象征矛盾、差异、调和，寓意凡事需正视差异，求同存异，以智慧调和矛盾',
    39 => '蹇卦代表险阻，象征困难、进退、互助，寓意凡事需直面困难，团结互助，循序渐进突破险阻',
    40 => '解卦代表解脱，象征脱困、舒缓、解决，寓意凡事需抓住时机，化解矛盾，摆脱困境重获新生',
    41 => '损卦代表减损，象征取舍、克制、付出，寓意凡事需懂得取舍，克制欲望，以小损换大益',
    42 => '益卦代表增益，象征受益、互助、成长，寓意凡事需互利共赢，乐于助人，在付出中获得成长',
    43 => '夬卦代表决断，象征果断、除弊、前行，寓意凡事需当机立断，清除障碍，勇往直前',
    44 => '姤卦代表相遇，象征机缘、选择、谨慎，寓意凡事需把握机缘，但保持谨慎，择善而交',
    45 => '萃卦代表聚集，象征团结、汇聚、顺势，寓意凡事需凝聚人心，顺势而为，促成事物的聚合',
    46 => '升卦代表上升，象征成长、积累、顺势，寓意凡事需循序渐进，积累实力，顺势攀升',
    47 => '困卦代表困境，象征艰难、坚守、自省，寓意凡事需在困境中坚守正道，自省求变，静待脱困',
    48 => '井卦代表水井，象征滋养、坚守、革新，寓意凡事需如井水般滋养他人，坚守本分，适时革新',
    49 => '革卦代表变革，象征革新、突破、顺应，寓意凡事需顺应时势，勇于变革，打破旧格局建立新秩序',
    50 => '鼎卦代表鼎新，象征稳定、传承、革新，寓意凡事需在稳定的基础上传承创新，鼎故革新',
    51 => '震卦代表雷，象征震动、警醒、行动，寓意凡事需保持警醒，闻风而动，以行动力应对变化',
    52 => '艮卦代表山，象征静止、坚守、界限，寓意凡事需懂得止境，坚守原则，不越界不妄动',
    53 => '渐卦代表渐进，象征稳步、积累、成长，寓意凡事需循序渐进，日积月累，逐步达成目标',
    54 => '归妹卦代表嫁妹，象征婚嫁、和谐、顺应，寓意凡事需顺应自然规律，注重阴阳调和与家庭和谐',
    55 => '丰卦代表丰盛，象征繁荣、戒骄、守正，寓意凡事需珍惜繁荣局面，戒骄戒躁，坚守正道',
    56 => '旅卦代表旅居，象征漂泊、适应、谨慎，寓意凡事需适应环境，谨慎行事，在漂泊中保持初心',
    57 => '巽卦代表风，象征顺从、渗透、沟通，寓意凡事需灵活应变，善于沟通，以柔克刚渗透目标',
    58 => '兑卦代表泽，象征喜悦、沟通、友善，寓意凡事需保持乐观，友善沟通，以和悦之心待人接物',
    59 => '涣卦代表涣散，象征离散、凝聚、治理，寓意凡事需凝聚人心，化解涣散，以秩序稳定局面',
    60 => '节卦代表节制，象征约束、适度、守度，寓意凡事需懂得节制，把握分寸，不逾矩不放纵',
    61 => '中孚卦代表诚信，象征真诚、信任、坚守，寓意凡事需心怀诚信，坚守承诺，以真诚赢得信任',
    62 => '小过卦代表小过，象征小偏差、收敛、谨慎，寓意凡事需警惕小过失，收敛锋芒，谨慎行事',
    63 => '既济卦代表既济，象征成功、守成、戒骄，寓意凡事需在成功后保持警醒，守成防败，不可懈怠',
    64 => '未济卦代表未济，象征未完成、进取、循环，寓意凡事需认识到事物的无限性，持续进取，循环提升'
    // 此处省略后续卦象数据，保持原有完整即可
];

// ------------- 报告生成主逻辑（示例）-------------
function generate_report() {
    // 1. 生成密码
    $password = generate_password();
    
    // 2. 收集报告数据
    $reportData = [
        'doc_password' => $password,
        'user_ip' => get_real_ip(),
        // ...其他报告数据...
    ];
    
    // 3. 插入数据库（会自动触发日志记录和邮件发送）
    $insertResult = insert_report_data($reportData);
    
    if ($insertResult) {
        // 4. 生成加密PDF（使用相同密码）
        generate_encrypted_pdf($reportData, $password);
        return [
            'success' => true,
            'reportId' => $GLOBALS['conn']->insert_id,
            'password' => $password // 实际场景中可能不返回给用户，仅通过邮件发送
        ];
    }
    
    return ['success' => false];
}

function generate_comprehensive_suggestions($hexagram_number, $processed_inquiry, $hexagram_meanings) {
    $core_meaning = $hexagram_meanings[$hexagram_number] ?? $hexagram_meanings[1];
    preg_match('/象征(.*?)，寓意(.*?)$/u', $core_meaning, $matches);
    $symbol_word = $matches[1] ?? '进取';
    $moral_word = $matches[2] ?? '坚守正道';

    $inquiry_tip = !empty($processed_inquiry) ? "针对你关注的“{$processed_inquiry}”，" : "";

    $suggest1 = "{$inquiry_tip}可遵循{$symbol_word}的卦象指引，将{$moral_word}的理念落实到具体行动中。建议先拆解当前目标为3个可落地的小步骤，今日优先完成最基础的一步，以实际行动打破停滞，逐步积累正向反馈，避免陷入空想或犹豫。";
    $suggest2 = "{$inquiry_tip}需保持与{$symbol_word}相契合的心态，牢记{$moral_word}的核心准则。面对阻碍时，不必急于求成，可暂时放缓节奏观察局势变化，以平和之心接纳过程中的起伏，相信卦象所预示的发展规律会带来转机。";
    $suggest3 = "{$inquiry_tip}可将{$symbol_word}作为长期行为准则，以{$moral_word}为底线定期复盘自身选择。建议每月梳理一次行动与卦象智慧的契合度，及时调整偏差，让长期坚持的方向始终贴合卦象所指引的正道，逐步实现长远目标。";

    return [trim($suggest1), trim($suggest2), trim($suggest3)];
}

// ------------- 六十四卦核心关键词 -------------
$hexagram_keywords = [
    1 => '自强不息', 2 => '厚德载物', 3 => '阴阳和合', 4 => '守正待机',
    5 => '循序渐进', 6 => '顺势而为', 7 => '刚健笃实', 8 => '亲比和谐',
    9 => '小畜积德', 10 => '履道坦坦', 11 => '天地交泰', 12 => '否极泰来',
    13 => '同人同心', 14 => '大有盛德', 15 => '谦谦君子', 16 => '豫乐和顺',
    17 => '随顺自然', 18 => '除弊兴利', 19 => '临事制宜', 20 => '观物明理',
    21 => '惩恶扬善', 22 => '文饰亨通', 23 => '剥尽复来', 24 => '一元复始',
    25 => '无妄而正', 26 => '畜聚以德', 27 => '颐养正道', 28 => '大过革新',
    29 => '险中求胜', 30 => '光明普照', 31 => '感通万物', 32 => '恒守其德',
    33 => '退隐避险', 34 => '大壮守正', 35 => '升进有道', 36 => '明夷守晦',
    37 => '家庭和睦', 38 => '睽而求合', 39 => '蹇中求进', 40 => '解厄脱困',
    41 => '损以修德', 42 => '益以兴利', 43 => '决而能断', 44 => '遇合之时',
    45 => '聚众合志', 46 => '升泰有道', 47 => '困而求通', 48 => '井养不穷',
    49 => '革故鼎新', 50 => '鼎定基业', 51 => '震动警励', 52 => '静止守正',
    53 => '渐进而升', 54 => '归妹守礼', 55 => '丰亨豫大', 56 => '旅道亨通',
    57 => '巽顺行事', 58 => '兑悦和乐', 59 => '涣而聚之', 60 => '节制有度',
    61 => '诚信立身', 62 => '小过从宜', 63 => '功成守正', 64 => '未济待时'
];

// ------------- 引入TCPDF -------------
if (file_exists('tcpdf/tcpdf.php')) {
    require_once 'tcpdf/tcpdf.php';
} else {
    die('错误：未找到TCPDF文件，请下载后放置到tcpdf文件夹！');
}

// ------------- 自定义TCPDF类（解决中文显示问题）-------------
class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('stsongstdlight', '', 16);
        $this->Cell(0, 15, '易经六十四卦象分析报告', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(8);
        $this->Cell(0, 10, '—————— 卦象解读 · 精准分析 ——————', 0, false, 'C', 0, '', 0, false, 'M', 'M');
    }

    public function Footer() {
        $this->SetY(-12);
        $this->SetFont('stsongstdlight', '', 10);
        $this->Cell(0, 10, '页码：'.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// ------------- 处理下载报告的逻辑 -------------
$download_error = '';
if (isset($_POST['download_report'])) {
    $session_data = is_array($_SESSION['report_data']) ? $_SESSION['report_data'] : [];
    $input_password = trim($_POST['input_password'] ?? '');
    $stored_password = $session_data['random_password'] ?? '';
    $pdf_file_path = $session_data['pdf_file_path'] ?? '';

    if (empty($stored_password) || empty($pdf_file_path) || !file_exists($pdf_file_path)) {
        $download_error = '报告数据不存在，请先生成报告！';
    } elseif ($input_password !== $stored_password) {
        $download_error = '密码错误，请核对邮箱中的密码！';
    } else {
        ob_end_clean();
        $filename = basename($pdf_file_path);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($pdf_file_path));
        readfile($pdf_file_path);
        exit;
    }
}

// ------------- 卦数生成逻辑（优化版）-------------
$hexagram_number = 0;
$hex_duplicate_tip = '';

if (isset($_SESSION['current_hex_number']) && is_numeric($_SESSION['current_hex_number'])) {
    $hexagram_number = (int)$_SESSION['current_hex_number'];
    $hex_duplicate_tip = $_SESSION['hex_duplicate_tip'] ?? '';
} else {
    $last_hex_number = isset($_SESSION['last_hex_number']) ? (int)$_SESSION['last_hex_number'] : 0;

    $max_attempts = 3;
    $attempts = 0;
    do {
        $new_hex_number = mt_rand(1, 64);
        $attempts++;
        if ($new_hex_number == $last_hex_number && $attempts >= $max_attempts) {
            $hex_duplicate_tip = '您坚定的想咨询这个问题，请仔细关注报告中的综合建议';
            break;
        }
    } while ($new_hex_number == $last_hex_number && $attempts < $max_attempts);

    $hexagram_number = $new_hex_number;

    $_SESSION['current_hex_number'] = $hexagram_number;
    $_SESSION['last_hex_number'] = $hexagram_number; // 原代码是赋值为$last_hex_number，改为当前卦数
    $_SESSION['hex_duplicate_tip'] = $hex_duplicate_tip;
}

$hexagram_number = ($hexagram_number >= 1 && $hexagram_number <= 64) ? $hexagram_number : mt_rand(1, 64);

// ------------- 读取卦象数据 -------------
$json_file = __DIR__ . '/data/yijing_data.json';
$hexagrams_raw = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : [];
$hexagrams = is_array($hexagrams_raw) ? $hexagrams_raw : [];
if (!empty($hexagrams)) {
    $hexagrams = array_combine(array_map('intval', array_keys($hexagrams)), $hexagrams);
}

// 构建当前卦数据
$current_hex_raw = isset($hexagrams[$hexagram_number]) && is_array($hexagrams[$hexagram_number]) ? $hexagrams[$hexagram_number] : [];
$current_hex = [
    'chinese_name' => $current_hex_raw['chinese_name'] ?? $DEFAULT_HEX_INFO['chinese_name'],
    'english_name' => $current_hex_raw['english_name'] ?? $DEFAULT_HEX_INFO['english_name'],
    'image' => $current_hex_raw['image'] ?? $DEFAULT_HEX_INFO['image'],
    'judgment' => $current_hex_raw['judgment'] ?? $DEFAULT_HEX_INFO['judgment'],
    'image_comment' => $current_hex_raw['image_comment'] ?? $DEFAULT_HEX_INFO['image_comment'],
    'hexagram' => $current_hex_raw['hexagram'] ?? $DEFAULT_HEX_INFO['hexagram'],
    'lines' => is_array($current_hex_raw['lines']) ? $current_hex_raw['lines'] : [],
    'interpretation' => is_array($current_hex_raw['interpretation']) ? $current_hex_raw['interpretation'] : $DEFAULT_INTERPRETATION,
    'explain' => $current_hex_raw['explain'] ?? $DEFAULT_HEX_INFO['explain'],
    'good_bad' => $current_hex_raw['good_bad'] ?? $DEFAULT_HEX_INFO['good_bad']
];
$current_hex['interpretation'] = array_merge($DEFAULT_INTERPRETATION, $current_hex['interpretation']);

if (empty($current_hex['explain']) || $current_hex['explain'] === '暂无卦解') {
    $core_meaning = $hexagram_meanings[$hexagram_number] ?? $hexagram_meanings[1];
    $current_hex['explain'] = "{$current_hex['chinese_name']}：{$core_meaning}。此卦寓意深远，需结合具体情境领悟其内涵。";
}
if (empty($current_hex['good_bad']) || $current_hex['good_bad'] === '平') {
    $good_bad_list = ['吉', '吉', '平', '凶', '平', '吉'];
    $current_hex['good_bad'] = $good_bad_list[$hexagram_number % 6];
}

// ------------- 拆分上下卦 -------------
$hexagram_str = (string)$current_hex['hexagram'];
$delimiters = ['|', '/', '-', ' '];
$hexagram_parts = [];
foreach ($delimiters as $delimiter) {
    $parts = explode($delimiter, $hexagram_str);
    if (count($parts) >= 2) {
        $hexagram_parts = $parts;
        break;
    }
}
if (empty($hexagram_parts)) {
    $len = mb_strlen($hexagram_str, 'UTF-8');
    if ($len >= 2) {
        $hexagram_parts = [
            mb_substr($hexagram_str, 0, floor($len/2), 'UTF-8'),
            mb_substr($hexagram_str, floor($len/2), null, 'UTF-8')
        ];
    } else {
        $hexagram_parts = ['☰', '☷'];
    }
}
$upper_gua = trim($hexagram_parts[0]) ?: '☰';
$lower_gua = trim($hexagram_parts[1] ?? '') ?: '☷';

// ------------- 获取卦关键词 -------------
$top_keyword = $hexagram_keywords[$hexagram_number] ?? '暂无关键词';

// ------------- 预提取展示变量 -------------
$hex_chinese = $current_hex['chinese_name'] ?? '未知卦';
$hex_english = $current_hex['english_name'] ?? 'Unknown';
$hex_image = $current_hex['image'] ?? '暂无卦辞';
$hex_judgment = $current_hex['judgment'] ?? '暂无彖辞';
$hex_comment = $current_hex['image_comment'] ?? '暂无象辞';
$hex_lines = $current_hex['lines'];
$hex_explain = $current_hex['explain'] ?? '暂无卦解';
$hex_good_bad = $current_hex['good_bad'] ?? '平';
$interpret_fortune = $current_hex['interpretation']['fortune'] ?? '暂无解读';
$interpret_wealth = $current_hex['interpretation']['wealth'] ?? '暂无解读';
$interpret_career = $current_hex['interpretation']['career'] ?? '暂无解读';
$interpret_marriage = $current_hex['interpretation']['marriage'] ?? '暂无解读';
$interpret_health = $current_hex['interpretation']['health'] ?? '暂无解读';
$interpret_lifespan = $current_hex['interpretation']['lifespan'] ?? '暂无解读';
$key_map_fortune = $DEFAULT_KEY_MAP['fortune'] ?? '时运';
$key_map_wealth = $DEFAULT_KEY_MAP['wealth'] ?? '财富';
$key_map_career = $DEFAULT_KEY_MAP['career'] ?? '事业';
$key_map_marriage = $DEFAULT_KEY_MAP['marriage'] ?? '婚姻';
$key_map_health = $DEFAULT_KEY_MAP['health'] ?? '健康';
$key_map_lifespan = $DEFAULT_KEY_MAP['lifespan'] ?? '寿命';

// ------------- 获取IP和天气信息（修复后的函数）-------------
$user_ip = get_real_ip();
$ip_info = get_ip_weather_info($user_ip);

// ------------- 处理生成报告的逻辑 -------------
$mail_tip = '';
if (isset($_POST['generate_report'])) {
    $main_inquiry = trim($_POST['main_inquiry'] ?? '无');
    $processed_inquiry = process_main_inquiry($main_inquiry, $forbidden_words);
    $random_password = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8);

    $log_content = date('Y-m-d H:i:s') . " - 卦数：{$hexagram_number} - 密码：{$random_password} - 主询：{$main_inquiry}\r\n";
    file_put_contents(PASSWORD_LOG_FILE, $log_content, FILE_APPEND | LOCK_EX);

    $pdf_filename = '易经' . $hexagram_number . '卦分析报告_' . date('YmdHis') . '.pdf';
    $pdf_file_path = REPORT_DIR . $pdf_filename;

    // ========== 调用AI生成卦解 ==========
    $ai_hex_explain = ai_generate_hexagram_explanation(
        $hexagram_number,
        [
            'chinese_name' => $hex_chinese,
            'location' => $ip_info['address'],
            'main_inquiry' => $main_inquiry
        ],
        $hexagram_meanings
    );
    $hex_explain = $ai_hex_explain;
    $current_hex['explain'] = $ai_hex_explain;

    // 生成PDF并保存到服务器
    $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(true, 15);

    $pdf->SetProtection(['print', 'copy'], $random_password, $random_password);

    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('易经卦象报告系统');
    $pdf->SetTitle('易经'.$hexagram_number.'卦分析报告');
    $pdf->SetSubject($hex_chinese . '卦解读');
    $key_map_array = $DEFAULT_KEY_MAP;
    $keywords = $top_keyword . ',' . implode(',', array_values($key_map_array));
    $pdf->SetKeywords($keywords);

    $pdf->SetFont('stsongstdlight', '', 10);
    $pdf->AddPage();

    // --------------- PDF内容布局 ---------------
    $page_width = $pdf->GetPageWidth() - 30;

    // 1. 报告基础信息
    $pdf->SetFillColor(52, 152, 219);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($page_width, 18, '报告基础信息', 1, 1, 'C', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(3);

    $report_time = date('Y-m-d H:i:s');
    $info_data = [
        ['报告时间：', $report_time],
        ['用户IP：', $ip_info['ip']],
        ['地理位置：', $ip_info['address']],
        ['当前天气：', $ip_info['current_weather']],
        ['主询内容：', $main_inquiry],
        ['核心关键词：', $top_keyword]
    ];
    $label_width = $page_width * 0.25;
    $content_width = $page_width * 0.75;
    foreach ($info_data as $row) {
        $pdf->Cell($label_width, 12, $row[0], 1, 0, 'R');
        $pdf->Cell($content_width, 12, $row[1], 1, 1, 'L');
    }
    $pdf->Ln(5);

    // 2. 卦象核心信息
    $pdf->SetFillColor(248, 249, 250);
    $pdf->Cell($page_width, 18, '卦象核心信息', 1, 1, 'C', true);
    $pdf->Ln(3);

    $pdf->SetFont('stsongstdlight', 'B', 14);
    $pdf->Cell($page_width, 15, $hexagram_number . '卦 - ' . $hex_chinese . '（' . $hex_english . '）', 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('stsongstdlight', '', 12);
    $pdf->MultiCell($page_width, 20, '原始卦象标识：' . $current_hex['hexagram'], 0, 'C');
    $pdf->Ln(3);

    $pdf->SetFont('stsongstdlight', '', 60);
    $pdf->SetTextColor(231, 76, 60);
    $pdf->Cell($page_width, 40, $upper_gua, 0, 1, 'C');
    $pdf->Cell($page_width, 40, $lower_gua, 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('stsongstdlight', '', 10);
    $pdf->Ln(5);

    // 3. 卦辞、彖辞、象辞
    $pdf->SetFillColor(232, 244, 253);
    $pdf->Cell($page_width, 15, '卦辞', 1, 1, 'L', true);
    $pdf->Ln(2);
    $pdf->MultiCell($page_width, 10, $hex_image, 1, 'L');
    $pdf->Ln(3);

    $pdf->SetFillColor(232, 244, 253);
    $pdf->Cell($page_width, 15, '彖辞', 1, 1, 'L', true);
    $pdf->Ln(2);
    $pdf->MultiCell($page_width, 10, $hex_judgment, 1, 'L');
    $pdf->Ln(3);

    $pdf->SetFillColor(232, 244, 253);
    $pdf->Cell($page_width, 15, '象辞', 1, 1, 'L', true);
    $pdf->Ln(2);
    $pdf->MultiCell($page_width, 10, $hex_comment, 1, 'L');
    $pdf->Ln(5);

    // 4. 爻辞
    $pdf->SetFillColor(245, 245, 245);
    $pdf->Cell($page_width, 15, '爻辞', 1, 1, 'L', true);
    $pdf->Ln(2);

    $yao_label_width = $page_width * 0.15;
    $yao_content_width = $page_width * 0.85;
    foreach ($hex_lines as $yao_key => $yao_content) {
        $line = trim($yao_content ?? '');
        if (!empty($line) && $line !== '暂无爻辞') {
            $pdf->Cell($yao_label_width, 12, $yao_key, 1, 0, 'R');
            $pdf->Cell($yao_content_width, 12, $line, 1, 1, 'L');
        }
    }
    $pdf->Ln(5);

    // 5. 卦解与吉凶
    $pdf->SetFillColor(245, 245, 245);
    $pdf->Cell($page_width, 15, '卦解与吉凶', 1, 1, 'L', true);
    $pdf->Ln(2);
    $pdf->MultiCell($page_width, 10, '卦解：' . $hex_explain, 1, 'L');
    $pdf->Ln(2);
    $pdf->MultiCell($page_width, 10, '吉凶：' . $hex_good_bad, 1, 'L');
    $pdf->Ln(5);

    // 6. 六项分析
    $pdf->SetFillColor(245, 245, 245);
    $pdf->Cell($page_width, 15, '六项分析', 1, 1, 'L', true);
    $pdf->Ln(2);

    $interpret_items = [
        'fortune' => ['时运', $interpret_fortune],
        'wealth' => ['财富', $interpret_wealth],
        'career' => ['事业', $interpret_career],
        'marriage' => ['婚姻', $interpret_marriage],
        'health' => ['健康', $interpret_health],
        'lifespan' => ['寿命', $interpret_lifespan]
    ];
    foreach ($interpret_items as $item) {
        $pdf->SetFont('stsongstdlight', 'B', 12);
        $pdf->Cell($page_width, 10, $item[0], 1, 1, 'L');
        $pdf->SetFont('stsongstdlight', '', 10);
        $pdf->MultiCell($page_width, 10, $item[1], 1, 'L');
        $pdf->Ln(2);
    }
    $pdf->Ln(5);

    // 7. 综合建议
    $suggestions = generate_comprehensive_suggestions($hexagram_number, $processed_inquiry, $hexagram_meanings);
    $pdf->SetFillColor(220, 230, 240);
    $pdf->Cell($page_width, 18, '综合建议', 1, 1, 'C', true);
    $pdf->Ln(3);
    $pdf->SetFont('stsongstdlight', '', 12);
    for ($i = 0; $i < count($suggestions); $i++) {
        $pdf->MultiCell($page_width, 12, "建议" . ($i + 1) . "：" . $suggestions[$i], 0, 'L');
        $pdf->Ln(3);
    }

    // 保存PDF到服务器
    $pdf->Output($pdf_file_path, 'F');

    // ------------- 准备数据库存储数据 -------------
    $report_data = [
        'doc_password' => $random_password,
        'report_time' => $report_time,
        'user_ip' => $ip_info['ip'],
        'location' => $ip_info['address'],
        'current_weather' => $ip_info['current_weather'],
        'main_inquiry' => $main_inquiry,
        'core_keyword' => $top_keyword,
        'hex_order' => $hexagram_number,
        'hex_name' => $hex_chinese,
        'hex_symbol' => $current_hex['hexagram'],
        'hex_canticle' => $hex_image,
        'tuan_canticle' => $hex_judgment,
        'xiang_canticle' => $hex_comment,
        'yao_canticle' => implode("\n", $hex_lines),
        'hex_explain' => $hex_explain,
        'good_bad' => $hex_good_bad,
        'analysis_fortune' => $interpret_fortune,
        'analysis_wealth' => $interpret_wealth,
        'analysis_career' => $interpret_career,
        'analysis_marriage' => $interpret_marriage,
        'analysis_health' => $interpret_health,
        'analysis_lifespan' => $interpret_lifespan,
        'suggestion1' => $suggestions[0],
        'suggestion2' => $suggestions[1],
        'suggestion3' => $suggestions[2]
    ];
    function insert_report_data($data) {
        $conn = connect_db();
        if (!$conn) {
            return false;
        }
    
        // 确保$data包含必要字段（根据你的业务需求调整字段名）
        $requiredFields = ['report_id', 'hexagram_number', 'main_inquiry', 'created_at'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                $conn->close();
                return false;
            }
        }
    
        // 预处理SQL，防止注入
        $stmt = $conn->prepare("
            INSERT INTO reports (
                report_id, hexagram_number, main_inquiry, location, 
                weather, created_at, password
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
    
        // 绑定参数（根据实际字段调整参数数量和类型）
        $stmt->bind_param(
            'sisssss',
            $data['report_id'],
            $data['hexagram_number'],
            $data['main_inquiry'],
            $data['location'],
            $data['weather'],
            $data['created_at'],
            $data['password']
        );
    
        $result = $stmt->execute();
        $stmt->close();
        $conn->close();
    
        return $result;
    }
    

    // 存储报告数据到SESSION
    $_SESSION['report_data'] = [
        'hexagram_number' => $hexagram_number,
        'hex_chinese' => $hex_chinese,
        'hex_english' => $hex_english,
        'hex_image' => $hex_image,
        'hex_judgment' => $hex_judgment,
        'hex_comment' => $hex_comment,
        'hex_lines' => $hex_lines,
        'hexagram_raw' => $current_hex['hexagram'],
        'upper_gua' => $upper_gua,
        'lower_gua' => $lower_gua,
        'ip_info' => $ip_info,
        'main_inquiry' => $main_inquiry,
        'top_keyword' => $top_keyword,
        'key_map' => $DEFAULT_KEY_MAP,
        'interpretation' => $current_hex['interpretation'],
        'random_password' => $random_password,
        'current_time' => $report_time,
        'pdf_file_path' => $pdf_file_path,
        'pdf_filename' => $pdf_filename
    ];

    $mail_tip = '您的报告已经生成，请向good_job_001@163.com发送邮件获取下载密码，祝您使用愉快，顺遂安康！';
}

/**
 * 从日志中获取报告对应的密码
 * @param string $reportId 报告ID
 * @return string|false 找到返回密码，否则返回false
 */
function get_password_by_report_id($reportId) {
    if (!file_exists(PASSWORD_LOG_FILE)) {
        return false;
    }
    // 读取日志文件内容
    $logContent = file_get_contents(PASSWORD_LOG_FILE);
    $lines = explode("\n", $logContent);
    // 反向遍历（最新记录在前）
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        $line = trim($lines[$i]);
        if (empty($line)) continue;
        // 匹配日志格式："时间 - 报告ID: xxx - 密码: yyy"
        if (preg_match("/报告ID: {$reportId} - 密码: ([a-zA-Z0-9]+)/", $line, $matches)) {
            return $matches[1]; // 返回匹配到的密码
        }
    }
    return false;
}

/**
 * 处理报告下载请求
 */
function handle_download_request() {
    // 检查是否有下载请求
    if (!isset($_POST['download_report']) || empty($_POST['report_id'])) {
        return; // 非下载请求，直接返回
    }
    
    $reportId = trim($_POST['report_id']);
    $userInputPassword = trim($_POST['report_password'] ?? '');
    
    // 验证参数
    if (empty($userInputPassword)) {
        $_SESSION['error'] = '请输入下载密码';
        header('Location: result.php?report_id=' . urlencode($reportId));
        exit;
    }
    
    // 获取该报告对应的正确密码
    $correctPassword = get_password_by_report_id($reportId);
    if (!$correctPassword) {
        $_SESSION['error'] = '报告不存在或密码记录丢失';
        header('Location: result.php?report_id=' . urlencode($reportId));
        exit;
    }
    
    // 密码验证（严格比较）
    if ($userInputPassword === $correctPassword) {
        // 密码正确，执行下载
        download_report($reportId);
    } else {
        // 密码错误
        $_SESSION['error'] = '密码错误，请重新输入';
        header('Location: result.php?report_id=' . urlencode($reportId));
        exit;
    }
}

/**
 * 下载报告文件
 * @param string $reportId 报告ID
 */
function download_report($reportId) {
    $reportFile = REPORT_DIR . $reportId . '.pdf'; // 假设报告文件以reportId命名
    if (!file_exists($reportFile)) {
        $_SESSION['error'] = '报告文件不存在';
        header('Location: result.php?report_id=' . urlencode($reportId));
        exit;
    }
    
    // 发送文件下载头
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="易经报告_' . $reportId . '.pdf"');
    header('Content-Length: ' . filesize($reportFile));
    readfile($reportFile);
    exit;
}

// 触发下载请求处理（放在文件末尾，确保所有函数定义后执行）
handle_download_request();

?>

<!-- 下载报告表单 -->
<?php if (isset($_GET['report_id'])): ?>
    <form method="POST" action="result.php">
        <!-- 隐藏域传递报告ID，确保验证时能关联到正确密码 -->
        <input type="hidden" name="report_id" value="<?php echo htmlspecialchars($_GET['report_id']); ?>">
        <label for="report_password">下载密码：</label>
        <input type="password" id="report_password" name="report_password" required>
        <button type="submit" name="download_report">下载报告</button>
    </form>
    
    <!-- 显示错误信息 -->
    <?php if (isset($_SESSION['error'])): ?>
        <p style="color: red;"><?php echo htmlspecialchars($_SESSION['error']); ?></p>
        <?php unset($_SESSION['error']); // 显示后清除，避免重复显示 ?>
    <?php endif; ?>
<?php endif; ?>


<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $hex_chinese . ' - 易经六十四卦详解'; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Microsoft YaHei", "SimHei", "STSong", sans-serif;
        }
        body {
            background-color: #f0f2f5;
            color: #333;
            line-height: 1.8;
            padding: 20px;
            background-image: linear-gradient(to bottom, #e8f4fd, #f0f2f5);
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            border: 1px solid #e6e6e6;
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            border-bottom: 3px solid #3498db;
            padding-bottom: 15px;
            margin-bottom: 30px;
            font-size: 32px;
            font-weight: bold;
        }
        h2 {
            color: #3498db;
            margin: 25px 0 15px;
            font-size: 24px;
            position: relative;
            padding-left: 15px;
        }
        h2::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 6px;
            height: 20px;
            background-color: #3498db;
            border-radius: 3px;
        }
        h3 {
            color: #2980b9;
            margin: 20px 0 10px;
            font-size: 20px;
        }
        .hexagram-symbol {
            text-align: center;
            margin: 40px 0;
            font-size: 56px;
            color: #e74c3c;
            line-height: 1.5;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        .line-item {
            margin: 12px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-left: 4px solid #9b59b6;
            border-radius: 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .interpretation-row {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
            gap: 20px;
        }
        .interpretation-item {
            flex: 1;
            min-width: 100px;
            background-color: #e8f4fd;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .interpretation-item:hover {
            transform: translateY(-5px);
        }
        .interpretation-item strong {
            color: #2980b9;
            font-size: 18px;
            display: block;
            margin-bottom: 8px;
        }
        .keyword-section {
            margin: 30px 0;
            padding: 20px;
            background-color: #fdf2e9;
            border-radius: 8px;
            border-left: 4px solid #e67e22;
        }
        .keyword-section p {
            font-size: 18px;
            color: #34495e;
        }
        .keyword-section span {
            color: #e74c3c;
            font-weight: bold;
            font-size: 20px;
        }
        .mail-tip {
            padding: 15px;
            background-color: #e8f5e9;
            border-radius: 6px;
            margin: 10px 0;
            color: #2e7d32;
            font-weight: bold;
            text-align: center;
        }
        .error-tip {
            padding: 15px;
            background-color: #ffebee;
            border-radius: 6px;
            margin: 10px 0;
            color: #c62828;
            font-weight: bold;
            text-align: center;
        }
        .duplicate-tip {
            padding: 15px;
            background-color: #fff3e0;
            border-radius: 6px;
            margin: 10px 0;
            color: #e65100;
            font-weight: bold;
            text-align: center;
        }
        .btn-group {
            margin: 30px 0;
            text-align: center;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            margin: 0 10px;
            background: linear-gradient(to right, #3498db, #2980b9);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        .btn-secondary {
            background: linear-gradient(to right, #95a5a6, #7f8c8d);
        }
        .report-section {
            margin: 35px 0;
            padding: 25px;
            background-color: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        .input-group {
            margin: 20px 0;
        }
        .input-group label {
            display: inline-block;
            width: 100px;
            font-weight: bold;
            font-size: 16px;
            color: #2c3e50;
        }
        .input-group input, .input-group textarea {
            width: 80%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: border 0.3s;
        }
        .input-group input:focus, .input-group textarea:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #3498db;
            text-decoration: none;
            font-size: 18px;
            transition: color 0.3s;
        }
        .back-link:hover {
            color: #2980b9;
            text-decoration: underline;
        }
        .fortune-btn {
            text-align: center;
            margin: 20px 0;
        }
        /* 新增：用户信息样式 */
        .user-info-section {
            margin: 20px 0;
            padding: 20px;
            background-color: #f5fafe;
            border-radius: 8px;
            border: 1px solid #dbeafe;
        }
        .user-info-section p {
            margin: 10px 0;
            font-size: 16px;
            color: #2c3e50;
        }
        .user-info-section span {
            color: #1976d2;
            font-weight: 600;
        }
        /* 调试信息样式（可选，上线后可注释） */
        .debug-section {
            margin: 20px 0;
            padding: 10px;
            background-color: #f0f8ff;
            border-radius: 4px;
            font-size: 12px;
            color: #666;
        }
        @media (max-width: 768px) {
            .interpretation-row {
                flex-direction: column;
                gap: 15px;
            }
            .container {
                padding: 25px;
            }
            h1 {
                font-size: 26px;
            }
            .hexagram-symbol {
                font-size: 48px;
            }
            .input-group input, .input-group textarea {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!empty($hex_duplicate_tip)): ?>
            <div class="duplicate-tip">
                <?php echo $hex_duplicate_tip; ?>
            </div>
        <?php endif; ?>

        <h1><?php echo $hexagram_number . '卦 - ' . $hex_chinese . '（' . $hex_english . '）'; ?></h1>

        <!-- 用户信息展示区域 -->
        <div class="user-info-section">
            <p><span>当前IP：</span><?php echo $ip_info['ip']; ?></p>
            <p><span>地理位置：</span><?php echo $ip_info['address']; ?></p>
            <p><span>实时天气：</span><?php echo $ip_info['current_weather']; ?></p>
            <p><span>报告生成时间：</span><?php echo date('Y-m-d H:i:s'); ?></p>
        </div>

        <div class="hexagram-symbol">
            <?php echo $upper_gua; ?><br>
            <?php echo $lower_gua; ?>
        </div>

        <div class="keyword-section">
            <p>您此时最应该体悟的是：<span><?php echo $top_keyword; ?></span></p>
        </div>

        <h2>卦辞</h2>
        <p><?php echo $hex_image; ?></p>

        <h2>彖辞</h2>
        <p><?php echo $hex_judgment; ?></p>

        <h2>象辞</h2>
        <p><?php echo $hex_comment; ?></p>

        <h2>爻辞</h2>
        <?php foreach ($hex_lines as $yao_key => $yao_content): ?>
            <?php $line = trim($yao_content ?? ''); ?>
            <?php if (!empty($line) && $line !== '暂无爻辞'): ?>
                <div class="line-item">
                    <strong><?php echo $yao_key; ?>：</strong><?php echo $line; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <h2>卦解与吉凶</h2>
        <p><?php echo $hex_explain; ?></p>
        <p><strong>吉凶：</strong><?php echo $hex_good_bad; ?></p>

        <h2>六项分析</h2>
        <div class="interpretation-row">
            <div class="interpretation-item">
                <strong><?php echo $key_map_fortune; ?></strong>
                <span><?php echo $interpret_fortune; ?></span>
            </div>
            <div class="interpretation-item">
                <strong><?php echo $key_map_wealth; ?></strong>
                <span><?php echo $interpret_wealth; ?></span>
            </div>
            <div class="interpretation-item">
                <strong><?php echo $key_map_career; ?></strong>
                <span><?php echo $interpret_career; ?></span>
            </div>
            <div class="interpretation-item">
                <strong><?php echo $key_map_marriage; ?></strong>
                <span><?php echo $interpret_marriage; ?></span>
            </div>
            <div class="interpretation-item">
                <strong><?php echo $key_map_health; ?></strong>
                <span><?php echo $interpret_health; ?></span>
            </div>
            <div class="interpretation-item">
                <strong><?php echo $key_map_lifespan; ?></strong>
                <span><?php echo $interpret_lifespan; ?></span>
            </div>
        </div>

        <div class="report-section">
            <h3>报告生成与下载</h3>
            <form method="post">
                <div class="input-group">
                    <label for="main_inquiry">主询：</label>
                    <textarea id="main_inquiry" name="main_inquiry" rows="3" placeholder="请输入您想要咨询的内容..."></textarea>
                </div>
                <div class="btn-group">
                    <button type="submit" name="generate_report" class="btn">生成报告</button>
                </div>
            </form>

            <?php if (!empty($mail_tip)): ?>
                <div class="mail-tip">
                    <?php echo $mail_tip; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($download_error)): ?>
                <div class="error-tip">
                    <?php echo $download_error; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_POST['generate_report']) || !empty($download_error)): ?>
                <hr style="border: 1px solid #eee; margin: 20px 0;">
                <form method="post">
                    <div class="input-group">
                        <label for="input_password">输入密码：</label>
                        <input type="text" id="input_password" name="input_password" placeholder="请输入你获得的下载密码..." required>
                    </div>
                    <div class="btn-group">
                        <button type="submit" name="download_report" class="btn btn-secondary">下载PDF报告</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <a href="index.php" class="back-link">← 返回</a>
    </div>
</body>
</html>
<?php
// 输出缓冲结束并刷新
ob_end_flush();
?>
