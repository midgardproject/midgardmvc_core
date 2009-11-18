<?php
/**
 * @package midcom_core
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * This modifier will return a formatted date string
 * usage:
 *      midcomdate_short: path/to/my/timestamp
 *
 * Formarts date with config var ['date_formats']['short']
 */
function phptal_tales_midcomDateShort($src, $nothrow)
{
    $src = trim($src);
    return 'strftime("' . $_MIDCOM->configuration->get('date_formats', 'short') . '", strtotime(' . PHPTAL_TalesInternal::path($src, $nothrow) . '))';
}

/**
 * This modifier will return a formatted date string
 * usage:
 *      midcomdate_short: path/to/my/timestamp
 *
 * Formarts date with config var ['date_formats']['long']
 */
function phptal_tales_midcomDateLong($src, $nothrow)
{
    $src = trim($src);
    return 'strftime("' . $_MIDCOM->configuration->get('date_formats', 'long') . '", strtotime(' . PHPTAL_TalesInternal::path($src, $nothrow) . '))';
}

/**
 * This modifier will return a formatted date string
 * usage:
 *      midcomdate_short: path/to/my/timestamp
 *
 * Formarts date with config var ['date_formats']['long']
 */
function phptal_tales_midcomDateRfc($src, $nothrow)
{
    $src = trim($src);
    return 'date(DATE_RFC3339, strtotime(' . PHPTAL_TalesInternal::path($src, $nothrow) . '))';
}

?>