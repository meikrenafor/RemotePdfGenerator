<?php

namespace RemotePdfGenerator\InvestmentSummaryBundle\Helper;

/**
 * Class RealPathHelper
 * @package InvestmentSummaryBundle\Helper
 */
class RealPathHelper
{
    /**
     * @param $from
     * @param $to
     * @return string
     */
    public static function relativePath($from, $to)
    {
        $fromPath = self::absolutePath($from);
        $toPath = self::absolutePath($to);

        $fromPathParts = explode(DIRECTORY_SEPARATOR, rtrim($fromPath, DIRECTORY_SEPARATOR));
        $toPathParts = explode(DIRECTORY_SEPARATOR, rtrim($toPath, DIRECTORY_SEPARATOR));
        while(count($fromPathParts) && count($toPathParts) && ($fromPathParts[0] == $toPathParts[0]))
        {
            array_shift($fromPathParts);
            array_shift($toPathParts);
        }
        return str_pad("", count($fromPathParts)*3, '..'.DIRECTORY_SEPARATOR).implode(DIRECTORY_SEPARATOR, $toPathParts);
    }

    /**
     * @param $path
     * @return mixed|string
     */
    public static function absolutePath($path)
    {
        $isEmptyPath    = (strlen($path) == 0);
        $isRelativePath = ($path{0} != '/');
        $isWindowsPath  = !(strpos($path, ':') === false);

        if (($isEmptyPath || $isRelativePath) && !$isWindowsPath)
            $path= getcwd().DIRECTORY_SEPARATOR.$path;

        // resolve path parts (single dot, double dot and double delimiters)
        $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        $pathParts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
        $absolutePathParts = array();
        foreach ($pathParts as $part) {
            if ($part == '.')
                continue;

            if ($part == '..') {
                array_pop($absolutePathParts);
            } else {
                $absolutePathParts[] = $part;
            }
        }
        $path = implode(DIRECTORY_SEPARATOR, $absolutePathParts);

        // resolve any symlinks
        if (file_exists($path) && linkinfo($path)>0)
            $path = readlink($path);

        // put initial separator that could have been lost
        $path= (!$isWindowsPath ? '/'.$path : $path);

        return $path;
    }
}
