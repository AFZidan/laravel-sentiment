<?php

namespace Zidan\LaravelSentiment;

use Illuminate\Support\Facades\Storage;

class Sentiment
{

    /**
     * Location of the dictionary files
     * @var str
     */
    protected $dataFolder = '';

    /**
     * List of tokens to ignore
     * @var array
     */
    protected $ignoreList = [];

    /**
     * List of words with negative prefixes, e.g. isn't, arent't
     * @var array
     */
    protected $negPrefixList = [];

    /**
     * Storage of cached dictionaries
     * @var array
     */
    protected $dictionary = [];

    /**
     * Min length of a token for it to be taken into consideration
     * @var int
     */
    protected $minTokenLength = 1;

    /**
     * Max length of a taken for it be taken into consideration
     * @var int
     */
    protected $maxTokenLength = 40;

    /**
     * Classification of opinions
     * @var array
     */
    protected $types = ['positive', 'negative', 'neutral', 'question'];

    /**
     * Token score per type
     * @var array
     */
    protected $typeTokCounts = [
        'positive' => 0,
        'negative' => 0,
        'neutral'  => 0,
        'question' => 0
    ];

    /**
     * Analyzed text score per type
     * @var array
     */
    protected $typeDocCounts = [
        'positive' => 0,
        'negative' => 0,
        'neutral'  => 0,
        'question' => 0
    ];

    /**
     * Number of tokens in a text
     * @var int
     */
    protected $tokCount = 0;

    /**
     * Number of analyzed texts
     * @var int
     */
    protected $docCount             = 0;
    private   $useDefaultDataFolder = true;
    /**
     * @var array
     */
    protected $prior = [
        'positive' => 0.25,
        'negative' => 0.25,
        'neutral'  => 0.25,
        'question' => 0.26
    ];


    protected $scoresKeywords = ['positive' => [], 'negative' => [], 'neutral' => [], 'question' => []];

    private $words = [];

    protected $scores = [];

    /**
     * Sentiment constructor.
     */
    protected function __construct()
    {

        $this->minTokenLength = config("laravel-sentiment.min_token_length");
        $this->maxTokenLength = config("laravel-sentiment.max_token_length");

        $this->types = ['positive', 'negative', 'neutral', 'question'];
        $this->typeDocCounts =
        $this->scores =
        $this->typeTokCounts = [
            'positive' => 0,
            'negative' => 0,
            'neutral'  => 0,
            'question' => 0
        ];

        $this->prior = [
            'positive' => 0.25,
            'negative' => 0.25,
            'neutral'  => 0.25,
            'question' => 0.26
        ];

        //set the base folder for the data models
        $this->setDataFolder(config("laravel-sentiment.data_folder", storage_path("sentiment-data")));

        //load and cache directories, get ignore and prefix lists
        $this->loadDefaults();
    }

    /**
     * Get scores for each type
     * @param $text
     * @return array
     */
    public function classify($text)
    {
        if ($this->scores) {
            return $this->scores;
        }

        //For each negative prefix in the list
        if ($this->negPrefixList) {

            foreach ($this->negPrefixList as $char) {
                //Search if that prefix is in the document
                if (strpos($text, $char) !== false) {
                    //remove the white space after the negative prefix
                    $text = str_replace($char . ' ', $char, $text);
                }
            }
        }


        $tokens = $this->_getTokens($text);

        // calculate the score in each category

        $total_score = 0;

        //Empty array for the scores for each of the possible categories
        $scores = [];

        //Loop through all of the different types set in the $types variable
        foreach ($this->types as $type) {
            //In the scores array add another dimention for the type and set it's value to 1. EG $scores->neg->1
            $scores[$type] = 1;

            //For each of the individual words used loop through to see if they match anything in the $dictionary
            foreach ($tokens as $i => $token) {
                //If statement so to ignore tokens which are either too long or too short or in the $ignoreList
                if (strlen($token) > $this->minTokenLength && strlen($token) < $this->maxTokenLength && !in_array($token, $this->ignoreList)) {

                    // check if previous token is negative prefix then current token will not be positive

                    if (isset($tokens[$i - 1]) && in_array($tokens[$i - 1], $this->negPrefixList)) {

                        $neg = config("laravel-sentiment.types.negative");
                        $this->scoresKeywords[$neg][] = "{$tokens[$i - 1]} {$token}";

                        // count up scores
                        $scores[$neg]++;
                        $total_score++;

                    } //If dictionary[token][type] is set
                    elseif (isset($this->dictionary[$type]) && in_array($token, $this->dictionary[$type])) {
                        // save decision keywords
                        $this->scoresKeywords[$type][] = $token;
                        // count up scores
                        $scores[$type]++;
                        $total_score++;
                    }

                }
            }

            //Score for this type is the prior probability multiplyied by the score for this type
//            $scores[$type] = $this->prior[$type] * $scores[$type];

        }

        if ($total_score > 0) {
            foreach ($this->types as $type) {
                $scores[$type] = round($scores[$type] / $total_score, 3);
            }
        }

        //Sort array in reverse order
        arsort($scores);
        $this->scores = $scores;
        return $scores;
    }

    private function getTokenByPosition($text, $position)
    {
        $start = strpos($text, ' ', -$position);
        $end = strpos($text, ' ', $position);
        return substr($text, $start, $end);
    }

    /**
     * Get the type of the text based on it's score
     *
     * @param String $text
     * @return String positive|negative|neutral|question
     */
    public function categorise($text)
    {
        $scores = $this->classify($text);
        //Classification is the key to the scores array
        $classification = key($scores);
        return $classification;
    }

    /**
     * Load and cache dictionary
     *
     * @param str $type
     * @return boolean
     */
    protected function setDictionary($type)
    {
        $typeName = array_search($type, config("laravel-sentiment.types"));

        $file = config("laravel-sentiment.data_files.{$type}");

        if (!Storage::exists($file)) {
            return abort(500, 'Data File does not exist: ' . $file . " - $type ");
        }

        $content = Storage::get($file);

        $this->words = unserialize($content);

        //Loop through all of the entries
        if (count($this->words)) {
            foreach ($this->words as $word) {

                $this->docCount++;

                $this->typeDocCounts[$type]++;

                //Trim word
                $word = trim($word);

                //If this word isn't already in the dictionary with this type
                if (!isset($this->dictionary[$type][$word])) {

                    $this->dictionary[$type][] = $word;
                }

                $this->typeTokCounts[$type]++;
                $this->tokCount++;
            }//Close while loop going through everyline in the text file
        }

        return true;
    }

    /**
     * Set the base folder for loading data models
     * @param str $dataFolder base folder
     * @param bool $loadDefaults true - load everything by default | false - just change the directory
     */
    protected function setDataFolder($dataFolder = false, $loadDefaults = false)
    {
        //if $dataFolder not provided, load default, else set the provided one
        if ($dataFolder == false) {
            $this->dataFolder = __DIR__ . '/data/';
        } else {
            $this->useDefaultDataFolder = false;
            if (file_exists($dataFolder)) {
                $this->dataFolder = $dataFolder;
            } else {
                abort(500, 'Error: could not find the directory - ' . $dataFolder);
            }
        }

        //load default directories, ignore and prefixe lists
        if ($loadDefaults !== false) {
            $this->loadDefaults();
        }
    }

    /**
     * Load and cache directories, get ignore and prefix lists
     */
    private function loadDefaults()
    {
        // Load and cache dictionaries
        foreach ($this->types as $type) {
            if (!$this->setDictionary($type)) {
                echo "Error: Dictionary for type '$type' could not be loaded";
            }
        }

        if (!isset($this->dictionary) || empty($this->dictionary))
            abort(500, 'Dictionaries not set');

        //Run function to get ignore list
        $this->ignoreList = $this->getList('ignore');

        //If ingnoreList not get give error message
        if (!isset($this->ignoreList))
            abort(500, 'Error: Ignore List not set');

        //Get the list of negative prefixes
        $this->negPrefixList = $this->getList('prefix');

        //If neg prefix list not set give error
        if (!isset($this->negPrefixList))
            return abort(500, 'Error: Negative Prefix List not set');
    }

    /**
     * Break text into tokens
     *
     * @param str $string String being broken up
     * @return array An array of tokens
     */
    private function _getTokens($string)
    {

        // Replace line endings with spaces
        $string = str_replace("\r\n", " ", $string);

        //Clean the string so is free from accents
        $string = $this->_cleanString($string);

        //Make all texts lowercase as the database of words in in lowercase
        $string = strtolower($string);
        $string = preg_replace('/[[:punct:]]+/', '', $string);

        //Break string into individual words using explode putting them into an array
        $stringArray = explode(' ', $string);
        $stringArray = array_where($stringArray, function ($str) {
            if ($str)
                return $str;
        });

        //Return array with each individual token
        return $stringArray;
    }

    /**
     * Load and cache additional word lists
     *
     * @param str $type
     * @return array
     */
    protected function getList($type)
    {
        //Set up empty word list array
        $wordList = $words = [];

        $typeName = array_search($type, config("laravel-sentiment.types"));

        $file = config("laravel-sentiment.data_files.{$typeName}");
        if (Storage::exists($file)) {
            $temp = Storage::get($file);
            $words = unserialize($temp);
        } /*else {

            return abort(500, 'File does not exist: ' . $file);
        }*/

        //Loop through results
        foreach ($words as $word) {
            //remove any slashes
            $word = stripcslashes($word);
            //Trim word
            $trimmed = trim($word);

            //Push results into $wordList array
            array_push($wordList, $trimmed);
        }

        //Return $wordList
        return $wordList;
    }

    /**
     * Function to clean a string so all characters with accents are turned into ASCII characters. EG: ‡ = a
     *
     * @param str $string
     * @return str
     */
    private function _cleanString($string)
    {

        //        $diac =
        //            /* A */
        //            chr(192) . chr(193) . chr(194) . chr(195) . chr(196) . chr(197) .
        //            /* a */
        //            chr(224) . chr(225) . chr(226) . chr(227) . chr(228) . chr(229) .
        //            /* O */
        //            chr(210) . chr(211) . chr(212) . chr(213) . chr(214) . chr(216) .
        //            /* o */
        //            chr(242) . chr(243) . chr(244) . chr(245) . chr(246) . chr(248) .
        //            /* E */
        //            chr(200) . chr(201) . chr(202) . chr(203) .
        //            /* e */
        //            chr(232) . chr(233) . chr(234) . chr(235) .
        //            /* Cc */
        //            chr(199) . chr(231) .
        //            /* I */
        //            chr(204) . chr(205) . chr(206) . chr(207) .
        //            /* i */
        //            chr(236) . chr(237) . chr(238) . chr(239) .
        //            /* U */
        //            chr(217) . chr(218) . chr(219) . chr(220) .
        //            /* u */
        //            chr(249) . chr(250) . chr(251) . chr(252) .
        //            /* yNn */
        //            chr(255) . chr(209) . chr(241);
        //
        //        return strtolower(strtr($string, $diac, 'AAAAAAaaaaaaOOOOOOooooooEEEEeeeeCcIIIIiiiiUUUUuuuuyNn'));

        $string = preg_replace('/[0-9]+/', '', $string);
        return $string;

    }

    /**
     * Deletes old data/data.* files
     * Creates new files from updated source fi
     */
    public function reloadDictionaries()
    {

        foreach ($this->types as $type) {
            $fn = "{$this->dataFolder}data.{$type}.php";
            if (file_exists($fn)) {
                unlink($fn);
            }
        }

        $dictionaries = __DIR__ . '/dictionaries/';

        foreach ($this->types as $type) {
            $dict = "{$dictionaries}source.{$type}.php";

            require_once( $dict );

            $data = $type;

            $fn = "{$this->dataFolder}data.{$type}.php";
            file_put_contents($fn, serialize($$data));
        }


    }

    public function getScoresKeywords($type = null)
    {
        if ($type && isset($this->scoresKeywords[$type])) {
            return $this->scoresKeywords[$type];
        }

        return $this->scoresKeywords;
    }

}

