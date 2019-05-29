<?php

namespace Zidan\LaravelSentiment;


class Analise extends Sentiment
{

    private $text = '';


    /**
     * Analyze constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param $text
     * @return $this
     */
    public function setText($text)
    {
        $this->text = $text;
        $this->scores = [];
        $this->scoresKeywords = ['positive' => [], 'negative' => [], 'neutral' => [], 'question' => []];
        return $this;
    }


    /**
     * Get the sentiment of a phrase.
     * @param null $text
     * @return String
     */
    public function decision($text = null)
    {
        if ($text)
            $this->setText($text);
        // Do not call functions so that we'll compute only one time
        return $this->categorise($this->text);

    }

    /**
     * Get scores for each decision.
     *
     * @param null $text
     * @return array An array containing keys 'negative', 'neutral' and 'positive' with a float. The closer to 1, the better
     *
     * @return array
     * @example ['negative' => 0.5, 'neutral' => 0.25, 'positive' => 0.25]
     */
    public function scores($text = null)
    {
        if ($text)
            $this->setText($text);

        $scores = $this->classify($this->text);

        $array = [];

        // We will remap to 'negative' / 'neutral' / 'positive' / 'question' and round with 2 digits
        foreach ($this->types as $value) {
            $array[$value] = round($scores[$value], 2);
        }
        return $array;
    }

    /**
     * Get the confidence of a decision for a result. The closer to 1, the better.
     *
     * @param string $text The given sentence
     *
     * @return float The confidence of a decision for a result. The close to 1, the better
     */
    public function score($text = null)
    {

        if ($text)
            $this->setText($text);
        $scores = $this->classify($this->text);
        return max($scores);
    }

    /**
     * Tells if a sentence is positive.
     *
     * @param string $string The given sentence
     *
     * @return bool
     */
    public function isPositive($string)
    {
        return $this->setText($string ?? $this->text)
                ->decision() == 'positive';
    }

    /**
     * Tells if a sentence is negative.
     *
     * @param string $string The given sentence
     *
     * @return bool
     */
    public function isNegative($string)
    {
        return $this->setText($string ?? $this->text)
                ->decision() == 'negative';
    }

    /**
     * Tells if a sentence is neutral.
     *
     * @param string $string The given sentence
     *
     * @return bool
     */
    public function isNeutral($string)
    {
        return $this->setText($string ?? $this->text)
                ->decision() == 'neutral';

    }

    /**
     * Tells if a sentence is neutral.
     *
     * @param string $string The given sentence
     *
     * @return bool
     */
    public function isQuestion($string = '')
    {

        return $this->setText($string ?? $this->text)
                ->decision() == 'question';
    }
}
