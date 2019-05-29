<?php
return [
    
    'types' => [
        'positive' => 'positive',
        'negative' => 'negative',
        'neutral' => 'neutral',
        'question' => 'question'
    ],
    'data_folder'      => storage_path("app/sentiment/ar"),
    'data_files'       => [
        'positive' => "sentiment/ar/data.positive.php",
        'negative' => "sentiment/ar/data.negative.php",
        'neutral'  => "sentiment/ar/data.neutral.php",
        'question' => "sentiment/ar/data.question.php",
        'prefix'   => "sentiment/ar/data.prefix.php",
        'ignore'   => "sentiment/ar/data.ignore.php"
    ],

];
