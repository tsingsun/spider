<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/19
 * Time: 下午6:02
 */

namespace tsingsun\spider\traits;


use yii\helpers\Console;

trait Screen
{
    /**
     * @var bool whether to run the command interactively.
     */
    public $interactive = true;
    /**
     * @var bool whether to enable ANSI color in the output.
     * If not set, ANSI color will only be enabled for terminals that support it.
     */
    public $color;
    /**
     * @var bool 是否在屏幕上显示日志
     */
    public $logShow = true;
    /**
     * Formats a string with ANSI codes.
     *
     * You may pass additional parameters using the constants defined in [[\yii\helpers\Console]].
     *
     * Example:
     *
     * ```
     * echo $this->ansiFormat('This will be red and underlined.', Console::FG_RED, Console::UNDERLINE);
     * ```
     *
     * @param string $string the string to be formatted
     * @return string
     */
    public function ansiFormat($string)
    {
        if ($this->isColorEnabled()) {
            $args = func_get_args();
            array_shift($args);
            $string = Console::ansiFormat($string, $args);
        }

        return $string;
    }

    /**
     * Prints a string to STDOUT.
     *
     * You may optionally format the string with ANSI codes by
     * passing additional parameters using the constants defined in [[\yii\helpers\Console]].
     *
     * Example:
     *
     * ```
     * $this->stdout('This will be red and underlined.', Console::FG_RED, Console::UNDERLINE);
     * ```
     *
     * @param string $string the string to print
     * @return int|bool Number of bytes printed or false on error
     */
    public function stdout($string)
    {
        if ($this->isColorEnabled()) {
            $args = func_get_args();
            array_shift($args);
            $string = Console::ansiFormat($string, $args);
        }
        return Console::stdout($string);
    }

    /**
     * Prints a string to STDERR.
     *
     * You may optionally format the string with ANSI codes by
     * passing additional parameters using the constants defined in [[\yii\helpers\Console]].
     *
     * Example:
     *
     * ```
     * $this->stderr('This will be red and underlined.', Console::FG_RED, Console::UNDERLINE);
     * ```
     *
     * @param string $string the string to print
     * @return int|bool Number of bytes printed or false on error
     */
    public function stderr($string)
    {
        if ($this->isColorEnabled(\STDERR)) {
            $args = func_get_args();
            array_shift($args);
            $string = Console::ansiFormat($string, $args);
        }

        return fwrite(\STDERR, $string);
    }

    /**
     * Prompts the user for input and validates it.
     *
     * @param string $text prompt string
     * @param array $options the options to validate the input:
     *
     *  - required: whether it is required or not
     *  - default: default value if no input is inserted by the user
     *  - pattern: regular expression pattern to validate user input
     *  - validator: a callable function to validate input. The function must accept two parameters:
     *      - $input: the user input to validate
     *      - $error: the error value passed by reference if validation failed.
     *
     * An example of how to use the prompt method with a validator function.
     *
     * ```php
     * $code = $this->prompt('Enter 4-Chars-Pin', ['required' => true, 'validator' => function($input, &$error) {
     *     if (strlen($input) !== 4) {
     *         $error = 'The Pin must be exactly 4 chars!';
     *         return false;
     *     }
     *     return true;
     * }]);
     * ```
     *
     * @return string the user input
     */
    public function prompt($text, $options = [])
    {
        if ($this->interactive) {
            return Console::prompt($text, $options);
        }

        return isset($options['default']) ? $options['default'] : '';
    }

    /**
     * Asks user to confirm by typing y or n.
     *
     * A typical usage looks like the following:
     *
     * ```php
     * if ($this->confirm("Are you sure?")) {
     *     echo "user typed yes\n";
     * } else {
     *     echo "user typed no\n";
     * }
     * ```
     *
     * @param string $message to echo out before waiting for user input
     * @param bool $default this value is returned if no selection is made.
     * @return bool whether user confirmed.
     * Will return true if [[interactive]] is false.
     */
    public function confirm($message, $default = false)
    {
        if ($this->interactive) {
            return Console::confirm($message, $default);
        }

        return true;
    }

    /**
     * Gives the user an option to choose from. Giving '?' as an input will show
     * a list of options to choose from and their explanations.
     *
     * @param string $prompt the prompt message
     * @param array $options Key-value array of options to choose from
     *
     * @return string An option character the user chose
     */
    public function select($prompt, $options = [])
    {
        return Console::select($prompt, $options);
    }

    /**
     * Returns a value indicating whether ANSI color is enabled.
     *
     * ANSI color is enabled only if [[color]] is set true or is not set
     * and the terminal supports ANSI color.
     *
     * @param resource $stream the stream to check.
     * @return bool Whether to enable ANSI style in output.
     */
    public function isColorEnabled($stream = \STDOUT)
    {
        return $this->color === null ? Console::streamSupportsAnsiColors($stream) : $this->color;
    }
}