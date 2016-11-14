<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Archiver interface file
 *
 * PHP version 5
 *
 * LICENSE: Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 * FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 * COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
 * IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @category  Utilities
 * @package   ZipFactory
 * @author    Yani Iliev <yani@iliev.me>
 * @copyright 2014 Yani Iliev
 * @license   https://raw.github.com/yani-/zip-factory/master/LICENSE The MIT License (MIT)
 * @version   GIT: 1.4.1
 * @link      https://github.com/yani-/zip-factory/
 */

/**
 * Archiver Interface
 *
 * @category  Tests
 * @package   ZipFactory
 * @author    Yani Iliev <yani@iliev.me>
 * @copyright 2014 Yani Iliev
 * @license   https://raw.github.com/yani-/zip-factory/master/LICENSE The MIT License (MIT)
 * @link      https://github.com/yani-/zip-factory/
 */
interface ArchiverInterface
{
    /**
     * Adds a file to a ZIP archive from the given path
     *
     * @param string $filename  The path to the file to add.
     * @param string $localname If supplied, this is the local name inside the ZIP archive that will override the filename.
     * @param int    $start     This parameter is not used but is required to extend ZipArchive.
     * @param int    $length    This parameter is not used but is required to extend ZipArchive.
     *
     * @return boolean
     */
    public function addFile($filename, $localname = null, $start = 0, $length = 0);

    /**
     * Adds a directory to a ZIP archive from the given path
     *
     * @param string $pathname  The path to the file to add.
     * @param string $localname If supplied, this is the local name inside the ZIP archive that will override the pathname.
     *
     * @return void
     */
    public function addDir($pathname, $localname = null);

    /**
     *  Add a file to a ZIP archive using its contents
     *
     * @param string $localname The name of the entry to create.
     * @param string $contents  The contents to use to create the entry. It is used in a binary safe mode.
     *
     * @return boolean
     */
    public function addFromString($localname , $contents);

    /**
     * Extract the archive contents
     *
     * @param string $destination Location where to extract the files.
     * @param mixed  $entities    The entries to extract. It accepts either a single entry name or an array of names.
     *
     * @return boolean
     */
    public function extractTo($destination, $entities = null);

    /**
     * Close the active archive (opened or newly created)
     *
     * @return boolean
     */
    public function close();
}
