<?php

function log_info($message) {
    $date = date('Y-m-d H:i:s');
    echo "{$date} > ${message}";

    $curr_month = date('Ym');
    file_put_contents("logs/{$curr_month}.log", "{$date} > ${message}", FILE_APPEND);
}

if(php_sapi_name() !== 'cli') {
    log_info("You are not allowed to access this feature. \n");
    exit;
}

require_once('vendor/autoload.php');
$config = require_once('config.php');

try{
    log_info("Getting snapshot of {$config['database']['database_name']}...\n");
    $file_name = "{$config['database']['database_name']}.gz";
    shell_exec("mysqldump -u {$config['database']['user']} --password='{$config['database']['password']}' -h {$config['database']['host']} -e --opt -c {$config['database']['database_name']} | gzip -c > {$file_name}");
    log_info("Finished getting snapshot of {$config['database']['database_name']}!\n");

    log_info("Starting upload to Amazon s3 ... \n");
    $s3 = new Aws\S3\S3Client([
        'version' => 'latest',
        'region'  => $config['s3']['region'],
        'credentials' => [
            'key' => $config['s3']['key'],
            'secret' => $config['s3']['secret']
        ]
    ]);

    $s3_key_name = "{$config['app_name']}/" . date('Y-m-d') . "-{$file_name}";
    log_info("Saving to file name {$s3_key_name} ...\n");
    $s3->putObject([
        'Bucket' => $config['s3']['bucket'],
        'Key' => $s3_key_name,
        'Body' => file_get_contents($file_name)
    ]);

    log_info("Finished upload!\n");
    log_info("https://{$config['s3']['host']}/{$config['s3']['bucket']}/{$s3_key_name}\n\n\n");
} catch(Exception $e) {
    log_info($e->getMessage() . "\n");
}
