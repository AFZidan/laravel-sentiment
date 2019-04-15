<?php
return [
    /**
     * Min length of a token for it to be taken into consideration
     */
    'min_token_length' => 1,
    /**
     * Max length of a token for it to be taken into consideration
     */
    'max_token_length' => 15,
    /**
     * set the base folder for the data models
     */
    'types' => [
        'positive' => 'pos',
        'negative' => 'neg',
        'neutral' => 'neu',
        'question' => 'que'
    ],
    'data_folder' => storage_path("app/sentiment/ar"),
    'data_files' => [
        'positive' => "sentiment/ar/data.pos.php",
        'negative' => "sentiment/ar/data.neg.php",
        'neutral' => "sentiment/ar/data.neu.php",
        'question' => "sentiment/ar/data.que.php",
        'prefix' => "sentiment/ar/data.prefix.php",
        'ignore' => "sentiment/ar/data.ign.php"
    ],

];