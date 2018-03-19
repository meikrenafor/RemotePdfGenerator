<?php

namespace RemotePdfGenerator\InvestmentSummaryBundle\Helper;

use Exception;
use iio\libmergepdf\Merger;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use JonnyW\PhantomJs\Client;
use JonnyW\PhantomJs\Http\PdfRequest;
use JonnyW\PhantomJs\Http\Response as PdfResponse;

/**
 * Class InvestmentSummaryHelper
 * @package InvestmentSummaryBundle\Helper
 */
class InvestmentSummaryHelper extends BaseControllerHelper
{
    const WKHTMLTOPDF_TOOL = 'wkhtmltopdf';

    const PHANTOMJS_TOOL = 'phantomjs';

    // ******************************************************************************************

    /**
     * @var array
     */
    private static $tools = [
        self::WKHTMLTOPDF_TOOL,
        self::PHANTOMJS_TOOL,
    ];

    /**
     * @var string
     */
    private static $toolInUse = self::PHANTOMJS_TOOL;

    /**
     * @var array
     */
    private static $brochures = [
        'kic',
        'kic-click',
        'kic-bookeye4v2',
        'kic-bookedge'
    ];

    // ******************************************************************************************

    /**
     * @param $url
     * @return array
     */
    private static function processCurrentDomain($url)
    {
        $patterns = [
            'http://www.',
            'https://www.',
            'http://',
            'https://'
        ];

        /** @var string $pattern */
        foreach ($patterns as $pattern) {
            $url = str_replace($pattern, '', $url);
        }

        return explode('/', $url);
    }

    // ******************************************************************************************

    /**
     * @return array|mixed
     */
    private static function getSummarySnappyArguments()
    {
        return [
            "enable-javascript" => true,
            "javascript-delay" => 2500,
            "no-stop-slow-scripts" => true,
            "no-background" => false,
            "lowquality" => false,
            "encoding" => "utf-8",
            "dpi" => 300,
            "image-dpi" => 300,
            "enable-external-links" => true,
            "enable-internal-links" => true,
            "margin-top" => 2,
            "margin-bottom" => 2,
            "margin-left" => 2,
            "margin-right" => 2,
//            "page-height" => '297mm',
//            "page-width" => '210mm',
//            "page-size" => 'A4',
            "print-media-type" => true
        ];
    }

    // ******************************************************************************************

    /**
     * @param ContainerInterface $container
     * @param $brochureLink
     * @return \Psr\Http\Message\StreamInterface
     */
    private static function getBrochureMarkup(ContainerInterface & $container, & $brochureLink)
    {
        $guzzleClient = $container->get('guzzle.client.api_crm');

        return $guzzleClient->get($brochureLink)->getBody();
    }

    /**
     * @param $paramsOrder
     * @return string
     */
    private static function getSummaryPdfBaseFileNameRemote(& $paramsOrder)
    {
        $code = '';
        $name = '';

        if ($paramsOrder) {
            if ($orderCode = strtoupper($paramsOrder['code'])) {
                $code = $orderCode;
            }

            if ($orderName = strtoupper($paramsOrder['name'])) {
                $name = $orderName;
            }
        }

        return $code . 'KIC' . $paramsOrder['date'] . $name . '.pdf';
    }

    /**
     * @param $summaryDirectory
     * @param $basePdfName
     * @return string
     */
    private static function getSummaryFinalFilePathRemote(& $summaryDirectory, & $basePdfName)
    {
        $filePath = $summaryDirectory . DIRECTORY_SEPARATOR . $basePdfName;

        $fs = new Filesystem();

        // if summary folder is not exist, make it
        if (!$fs->exists($summaryDirectory)) {
            $fs->mkdir($summaryDirectory);
        }

        $fs->chmod($summaryDirectory, 0777, 0);

        return $filePath;
    }

    /**
     * @param $brochure
     * @param $brochuresDirectory
     * @return string
     * @throws Exception
     */
    private static function getBrochureFilePathRemote(& $brochure, & $brochuresDirectory)
    {
        if (in_array($brochure, self::$brochures)) {

            $fs = new Filesystem();

            // if brochures directory is not exist, make it
            if (!$fs->exists($brochuresDirectory)) {
                $fs->mkdir($brochuresDirectory);
            }

            $fs->chmod($brochuresDirectory, 0777, 0);

            return $brochuresDirectory . DIRECTORY_SEPARATOR . $brochure . '.pdf';
        }

        throw new Exception('Unknown brochure.');
    }

    // ******************************************************************************************

    /**
     * @param ContainerInterface $container
     * @param $html
     * @param $filePath
     * @param null $snappyArgs
     */
    private static function generateSnappyPdfFileRemote(ContainerInterface & $container, & $html, & $filePath, $snappyArgs = null)
    {
        $container->get('knp_snappy.pdf')
            ->generateFromHtml($html, $filePath, $snappyArgs);
    }

    /**
     * @param $markup
     * @param $brochureFilePath
     * @param null $settings
     */
    private static function generateSnappyBrochurePdfFileRemote(& $markup, & $brochureFilePath, & $settings = null)
    {
        $snappyArgs = $settings ?: self::getSummarySnappyArguments();

        $fs = new Filesystem();

        if (!$fs->exists($brochureFilePath)) {
            self::generateSnappyPdfFileRemote($markup, $brochureFilePath, $snappyArgs);
        }
    }

    // ******************************************************************************************

    /**
     * @param $url
     * @param $filePath
     * @throws Exception
     */
    private function generatePhantomPdfFileRemote(& $url, & $filePath)
    {
        $client = Client::getInstance();

        $phantomJsPath = '../bin/phantomjs';

        // set path to phantomjs executable
        $client->getEngine()->setPath($phantomJsPath);

        // enable debug
        $client->getEngine()->debug(true);

        // ignore ssl errors
        $client->getEngine()->addOption('--ignore-ssl-errors=true');
        $client->getEngine()->addOption('--ssl-protocol=any');

        // tells the client to wait for all resources before rendering
        $client->isLazy();

        /** @var PdfRequest $request */
        $request = $client->getMessageFactory()->createPdfRequest($url, 'GET');

        $request->setOutputFile($filePath);
        $request->setFormat('A4');
        $request->setOrientation('portrait');
        $request->setPaperSize('1250px', '1800px');

//        $request->setTimeout(20000);

        /** @var PdfResponse $response */
        $response = $client->getMessageFactory()->createResponse();

        // Send the request
        $client->send($request, $response);

        $statusCode = $response->getStatus();
        if ($statusCode !== 200) {
            throw new Exception(/*'PhantomJS Status Code - ' . $statusCode*/ $client->getLog());
        }
    }

    /**
     * @param $url
     * @param $brochureFilePath
     */
    private function generatePhantomBrochurePdfFileRemote(& $url, & $brochureFilePath)
    {
        $fs = new Filesystem();

        if (!$fs->exists($brochureFilePath)) {
            self::generatePhantomPdfFileRemote($url, $brochureFilePath);
        }
    }

    // ******************************************************************************************

    /**
     * @throws Exception
     */
    private static function validateToolInUse()
    {
        if (!in_array(self::$toolInUse, self::$tools)) {
            throw new Exception('Invalid tool defined (' . self::$toolInUse . ').');
        }
    }

    /**
     * @return bool
     */
    private static function wkhtmltopdfToolIsActive()
    {
        return self::$toolInUse === self::WKHTMLTOPDF_TOOL;
    }

    /**
     * @return bool
     */
    private static function phantomJsToolIsActive()
    {
        return self::$toolInUse === self::PHANTOMJS_TOOL;
    }

    // ******************************************************************************************

    /**
     * @param $html
     * @param $parsed
     * @param ContainerInterface $container
     * @param null $settings
     */
    private static function generatePdfFromHtmlRemote(ContainerInterface & $container, & $html, & $parsed, & $settings = null)
    {
        $snappyArgs = $settings ?: self::getSummarySnappyArguments();

        $fs = new Filesystem();

        if (array_key_exists('brochureLink', $parsed) && !is_null($parsed['brochureLink'])) {
            if (array_key_exists('updateBrochures', $parsed) && $parsed['updateBrochures'] === true) {
                $domain = $parsed['domain'];

                // tool check
                self::validateToolInUse();

                /** @var string $brochure */
                foreach (self::$brochures as $brochure) {
                    $url = 'http://' . $domain . '/brochure/' . $brochure . '/pdf';
                    $path = self::getBrochureFilePathRemote($brochure, $parsed['brochuresDirectory']);

                    if (!$fs->exists($path)) {

                        // knp snappy / wkhtmltopdf
                        if (self::wkhtmltopdfToolIsActive()) {
                            $content = self::getBrochureMarkup($container, $url);
                            self::generateSnappyBrochurePdfFileRemote($content, $path);
                        }

                        // phantomjs
                        if (self::phantomJsToolIsActive()) {
                            self::generatePhantomBrochurePdfFileRemote($url, $path);
                        }
                    }
                }
            } else {
                if (!$fs->exists($parsed['brochureFilePath'])) {

                    // knp snappy / wkhtmltopdf
                    if (self::wkhtmltopdfToolIsActive()) {
                        self::generateSnappyBrochurePdfFileRemote($parsed['brochureMarkup'], $parsed['brochureFilePath']);
                    }

                    // phantomjs
                    if (self::phantomJsToolIsActive()) {
                        self::generatePhantomBrochurePdfFileRemote($parsed['brochureLink'], $parsed['brochureFilePath']);
                    }
                }
            }
        }

        if (!$fs->exists($parsed['finalPdfPath'])) {

            // knp snappy / wkhtmltopdf
            if (self::wkhtmltopdfToolIsActive()) {
                self::generateSnappyPdfFileRemote($html, $parsed['finalPdfPath'], $snappyArgs);
            }

            // phantomjs
            if (self::phantomJsToolIsActive()) {
                self::generatePhantomPdfFileRemote($html, $parsed['finalPdfPath']);
            }

            if (array_key_exists('brochureLink', $parsed) && !is_null($parsed['brochureLink'])) {
                $m = new Merger();
                $m->addIterator([$parsed['finalPdfPath'], $parsed['brochureFilePath']]);

                file_put_contents($parsed['finalPdfPath'], $m->merge());
            }
        }
    }

    /**
     * @param ContainerInterface $container
     * @param $parsed
     * @return array
     * @throws Exception
     */
    private static function sendSummaryToEmailRemote(ContainerInterface & $container, & $parsed)
    {
        $to = null;
        if (array_key_exists('recipient', $parsed)) {
            $to = $parsed['recipient'];
        }

        if (is_null($to) || gettype($to) !== 'string' || $to === '') {
            throw new Exception('Field \'recipient\' is not specified or is invalid.');
        }

        /** @var \Swift_Transport_SpoolTransport $transport */
        $transport = $container->get('swiftmailer.transport.real');

        $message = \Swift_Message::newInstance($transport)
            ->setSubject('Requested Investment Summary Document from KIC Builder KIC.com')
            ->setFrom('KIC-Website@KICService.com')
            ->setTo($to);

        $message->attach(
            \Swift_Attachment::fromPath($parsed['finalPdfPath'])
                ->setFilename($parsed['basePdfName'])
        );

        $success = false;
        $reason = '';
        $details = '';

        try {
            $container->get('mailer')->send($message);
            $success = true;
        } catch (\Swift_RfcComplianceException $e) {
            $reason = 'SwiftMailer: Failed to send summary email! Reason: ' . $e->getMessage();
            $details = $e;
        }

        (new SwiftMailerSpoolFlushHelper($container))->flushSpoolQueue();

        return [
            'success' => $success,
            'reason' => $reason,
            'details' => $details,
            'fileName' => $parsed['basePdfName'],
            'recipient' => $parsed['recipient'],
        ];
    }

    /**
     * @param $parsed
     * @return array
     * @throws Exception
     */
    private static function disposePdfRemote(& $parsed)
    {
        $fs = new Filesystem();

        if ($fs->exists($parsed['finalPdfPath'])) {
            $fs->remove($parsed['finalPdfPath']);
        } else {
            throw new Exception('Unable to dispose Investment Summary document. File <' . $parsed['basePdfName'] . '> does not exist.');
        }

        return [
            'success' => true,
            'fileName' => $parsed['basePdfName']
        ];
    }

    // ******************************************************************************************

    /**
     * @param ContainerInterface $container
     * @param $params
     * @return array|null
     * @throws Exception
     */
    private static function processParamsRemote(ContainerInterface & $container, & $params)
    {
        // top check
        if (empty($params)) {
            throw new Exception('Required parameters are not specified.');
        }

        // action
        $action = null;
        if (array_key_exists('action', $params)) {
            $action = $params['action'];
        }

        if (is_null($action) || gettype($action) !== 'string' || $action === '') {
            throw new Exception('Field \'action\' is not specified or is invalid.');
        }

        $parsed = null;

        // **********************************************

        // page html
        $html = null;
        if (array_key_exists('html', $params)) {
            $html = $params['html'];
        }

        // page html
        $url = null;
        if (array_key_exists('url', $params)) {
            $url = $params['url'];
        }

        if (is_null($html) && is_null($url)) {
            throw new Exception('No fields \'url\' or \'html\' are specified.');
        }

        if (!is_null($html) && !is_null($url)) {
            throw new Exception('Only one field (\'url\' or \'html\') should be specified at one time.');
        }

        if (!is_null($html) && (gettype($html) !== 'string' || $html === '')) {
            throw new Exception('Field \'html\' is invalid.');
        }

        if (!is_null($url) && (gettype($url) !== 'string' || $url === '')) {
            throw new Exception('Field \'url\' is invalid.');
        }

        // **********************************************

        // summary pdf
        $order = null;
        if (array_key_exists('order', $params)) {
            $order = $params['order'];
        }

        if (is_null($order) || gettype($order) !== 'array' || count($order) !== 3) {
            throw new Exception('Field \'order\' is not specified or is invalid.');
        }

        $summaryDirectory = RealPathHelper::absolutePath($container->getParameter('kernel.root_dir') . '/../web/summary');
        $basePdfName = self::getSummaryPdfBaseFileNameRemote($order);
        $finalPdfPath = self::getSummaryFinalFilePathRemote($summaryDirectory, $basePdfName);

        // brochure
        $brochureLink = null;
        if (array_key_exists('brochureMarkup', $params)) {
            $brochureLink = $params['brochureMarkup'];
        }

        if (!is_null($brochureLink) && (gettype($brochureLink) !== 'string' || $brochureLink === '')) {
            throw new Exception('Field \'brochureMarkup\' type or value is invalid');
        }

        $brochureMarkup = null;
        $brochure = null;
        $brochuresDirectory = null;
        $brochuresFilePath = null;

        $domain = null;
        if (!is_null($brochureLink)) {
            $brochureMarkup = self::getBrochureMarkup($container, $brochureLink);
            $domainData = self::processCurrentDomain($brochureLink);

            $domain = array_shift($domainData);
            $brochure = end($domainData);

            $brochuresDirectory = $summaryDirectory . DIRECTORY_SEPARATOR . 'brochures';
            $brochuresFilePath = self::getBrochureFilePathRemote($brochure, $brochuresDirectory);
        }

        // pdf generator settings
        $settings = null;
        if (array_key_exists('settings', $params) && gettype($params['settings']) === 'array' && !empty($params['settings'])) {
            $settings = $params['settings'];
        }

        $parsed = [
            'url' => $url,
            'action' => $action,
            'html' => $html,
            'domain' => $domain,
            'brochureLink' => $brochureLink,
            'brochureMarkup' => $brochureMarkup,
            'basePdfName' => $basePdfName,
            'finalPdfPath' => $finalPdfPath,
            'brochureFilePath' => $brochuresFilePath,
            'brochuresDirectory' => $brochuresDirectory,
            'updateBrochures' => true,
            'settings' => $settings,
        ];

        // recipient
        if (array_key_exists('recipient', $params)) {
            $parsed += [
                'recipient' => $params['recipient']
            ];
        }

        return $parsed;
    }

    // ******************************************************************************************

    /**
     * @param ContainerInterface $container
     * @param $params
     * @return array
     */
    public static function getOutputFromHtmlRemote(ContainerInterface & $container, $params)
    {
        $parsed = self::processParamsRemote($container, $params);

        $fs = new Filesystem();
        if ($fs->exists($parsed['finalPdfPath'])) {
            $fs->remove($parsed['finalPdfPath']);
        }

        // knp snappy / wkhtmltopdf
        if (self::wkhtmltopdfToolIsActive()) {
            if (array_key_exists('settings', $parsed)) {
                self::generatePdfFromHtmlRemote($container, $parsed['html'], $parsed, $parsed['settings']);
            } else {
                self::generatePdfFromHtmlRemote($container, $parsed['html'], $parsed);
            }
        }

        if (self::phantomJsToolIsActive()) {
            self::generatePdfFromHtmlRemote($container, $parsed['url'], $parsed);
        }

        return [
            'action' => $parsed['action'],
            'name' => $parsed['basePdfName'],
            'path' => $parsed['finalPdfPath'],
        ];
    }

    /**
     * @param ContainerInterface $container
     * @param $params
     * @return array
     */
    public static function sendPdfToEmailRemote(ContainerInterface & $container, $params)
    {
        $parsed = self::processParamsRemote($container, $params);

        $fs = new Filesystem();
        if ($fs->exists($parsed['finalPdfPath'])) {
            $fs->remove($parsed['finalPdfPath']);
        }

        if (array_key_exists('settings', $parsed)) {
            self::generatePdfFromHtmlRemote($container, $parsed['html'], $parsed, $parsed['settings']);
        } else {
            self::generatePdfFromHtmlRemote($container, $parsed['html'], $parsed);
        }

        return self::sendSummaryToEmailRemote($container, $parsed);
    }

    /**
     * @param ContainerInterface $container
     * @param $params
     * @return array
     */
    public static function disposeOutputPdfRemote(ContainerInterface & $container, $params)
    {
        $parsed = self::processParamsRemote($container, $params);

        return self::disposePdfRemote($parsed);
    }

    // ******************************************************************************************

    /**
     * @param ContainerInterface $container
     * @param $params
     * @return array
     */
    public static function getOutputFromHtmlFileNameRemote(ContainerInterface & $container, $params)
    {
        $parsed = self::processParamsRemote($container, $params);

        $fs = new Filesystem();

        return [
            'name' => $parsed['basePdfName'],
            'exists' => $fs->exists($parsed['finalPdfPath']),
        ];
    }

    // ******************************************************************************************
}
