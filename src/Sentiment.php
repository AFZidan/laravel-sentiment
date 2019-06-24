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
    protected $negPrefixList        = [];
    protected $negativePrefixTokens = [];
    protected $positivePrefixTokens = [];

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
     * List of tokens in a text
     * @var array
     */
    protected $textTokens = [];
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

        $this->textTokens = $this->_getTokens($text);

        $this->textTokens = $this->clearPrefix($text);

        // calculate the score in each category

        $total_score = 0;

        //Empty array for the scores for each of the possible categories
        $scores = [];

        //Loop through all of the different types set in the $types variable
        foreach ($this->types as $type) {
            //In the scores array add another dimention for the type and set it's value to 1. EG $scores->neg->1
            $scores[$type] = isset($scores[$type]) ? $scores[$type] : 0;

            //For each of the individual words used loop through to see if they match anything in the $dictionary
//dd($this->textTokens);
            foreach ($this->textTokens as $i => $token) {

                //If statement so to ignore tokens which are either too long or too short or in the $ignoreList
                if (strlen($token) >= $this->minTokenLength && strlen($token) <= $this->maxTokenLength && !in_array($this->textTokens, $this->ignoreList)) {

                    if (isset($this->dictionary[$type]) && in_array($token, $this->dictionary[$type])) {
                        // save decision keywords
                        $this->scoresKeywords[$type][] = $token;
                        // count up scores
                        $scores[$type]++;
                        $total_score++;
                    }
                }
            }

        }

        if ($total_score > 0) {

            foreach ($this->types as $type) {
                $scores[$type] = round($scores[$type] / $total_score, 3);
            }
        }

        //Sort array in reverse order
        arsort($scores);

        $this->scores = $scores;
        if ($this->scores['positive'] > 0 && $this->scores['positive'] == $this->scores['negative']) {

            $this->scores['negative'] += 0.01;
            $this->scores['positive'] -= 0.01;
        }

        return $this->scores;
    }

    public function clearPrefix($text)
    {
        $negativeTokens = [];
        $removableIndexes = [];
        //For each negative prefix in the list
        if ($this->negPrefixList) {

            foreach ($this->negPrefixList as $char) {
                //Search if that prefix is in the document
                if (strpos($text, $char) !== false && in_array($char, $this->textTokens)) {
//                    get all indexes of current character
                    $charIndexes = array_keys($this->textTokens, $char);

                    foreach ($charIndexes as $index) {
                        $nexIndex = $index + 1;

                        $next = $this->textTokens[$nexIndex] ?? "";
                        $token = trim("$char $next");
                        //next is positive token then add prefix and token to negative dictionary
                        if (in_array($next, $this->dictionary['positive'])) {
                            $removableIndexes[] = $nexIndex;
                            $this->dictionary['negative'][] = $token;
                            $this->negativePrefixTokens[] = $token;
                            $this->textTokens = array_replace($this->textTokens, [$index => $token]);
                        }

                        //next is positive token then add prefix and token to positive dictionary
                        if (in_array($next, $this->dictionary['negative'])) {
                            $removableIndexes[] = $nexIndex;
                            $this->dictionary['positive'][] = $token;
                            $this->positivePrefixTokens[] = $token;
                            $this->textTokens = array_replace($this->textTokens, [$index => $token]);

                        }


                    }

                }
            }
        }
        foreach ($removableIndexes as $i => $index) {
            array_splice($this->textTokens, $index - $i, 1);
        }

        return $this->textTokens;
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

        return array_search(max($scores), $scores);
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

        //Return reindexed array with each individual token
        return array_values($stringArray);
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

        $file = config("laravel-sentiment.data_files.{$type}");
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
     * accepts only arabic and english chars
     * @param str $string
     * @return str
     */
    private function _cleanString($string)
    {
        $string = preg_replace('/[^آأ-يءA-Za-z ]/ui', '', $string);
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
