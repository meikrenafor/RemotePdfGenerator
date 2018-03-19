<?php

namespace RemotePdfGenerator\InvestmentSummaryBundle\Controller;

use Exception;
use FOS\RestBundle\Controller\Annotations\Post;
use RemotePdfGenerator\InvestmentSummaryBundle\Helper\InvestmentSummaryHelper;
use RemotePdfGenerator\InvestmentSummaryBundle\Helper\JsonSerializerHelper;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class InvestmentSummaryController
 * @package InvestmentSummaryBundle\Controller
 */
class InvestmentSummaryController extends Controller
{
    /**
     * @param $data
     * @param int $status
     * @return Response
     */
    private function getSuccessResponse($data, $status = 200) {
        return new Response($data, $status, [
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * @param $data
     * @param int $status
     * @return Response
     */
    private function getErrorResponse($data, $status = 400) {
        return $this->getSuccessResponse('Error: ' . $data, $status);
    }

    /**
     * @param $data
     * @return Response
     */
    private function getSerializedResponse($data)
    {
        $serializer = new JsonSerializerHelper($this->container);
        $serialized = $serializer->getJson($data);

        $response = $this->getSuccessResponse($serialized);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * @param $data
     * @param $fileName
     * @return Response
     */
    private function getPdfDownloadResponse($data, $fileName) {
        return new Response($data, 200, [
            'Access-Control-Allow-Origin' => '*',
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment' . '; filename="' . $fileName,
        ]);
    }

    /**
     * @param $data
     * @param $fileName
     * @return Response
     */
    private function getPdfPreviewResponse($data, $fileName) {
        return new Response($data, 200, [
            'Access-Control-Allow-Origin' => '*',
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline' . '; filename="' . $fileName,
        ]);
    }

    // ******************************************************************************************

    /**
     * @Post("/remote/pdf/generate")
     *
     * [
     *  'html',
     *  'brochureMarkup',
     *  'order['name','code','date']',
     * ]
     */
    public function remotePdfGenerateAction(Request $request)
    {
        $params = $request->request->all();

        $result = null;
        try {
            $result = InvestmentSummaryHelper::getOutputFromHtmlRemote($this->container, $params);
        } catch (Exception $e) {
            return $this->getErrorResponse($e->getMessage());
        }

        $action = $result['action'];
        $name = $result['name'];
        $content = file_get_contents($result['path']);

        if ($action === 'preview') {
            return $this->getPdfPreviewResponse($content, $name);
        }

        if ($action === 'download') {
            return $this->getPdfDownloadResponse($content, $name);
        }

        return $this->getErrorResponse('Unrecognized action.');
    }

    /**
     * @Post("/remote/pdf/email")
     *
     * [
     *  'html',
     *  'brochureMarkup',
     *  'order['name','code','date']',
     *  'recipient',
     * ]
     */
    public function remotePdfSendAction(Request $request)
    {
        $params = $request->request->all();

        $result = null;
        try {
            $result = InvestmentSummaryHelper::sendPdfToEmailRemote($this->container, $params);
        } catch (Exception $e) {
            return $this->getErrorResponse($e->getMessage());
        }

        return $this->getSerializedResponse($result);
    }

    /**
     * @Post("/remote/pdf/dispose")
     *
     * [
     *  'brochureMarkup',
     *  'order['name','code','date']',
     * ]
     */
    public function remotePdfDisposeAction(Request $request)
    {
        $result = null;
        try {
            $result = InvestmentSummaryHelper::disposeOutputPdfRemote($this->container, $request->request->all());
        } catch (Exception $e) {
            return $this->getErrorResponse($e->getMessage());
        }

        return $this->getSerializedResponse($result);
    }

    // ******************************************************************************************

    /**
     * @Post("/remote/pdf/filename")
     *
     * [
     *  'html',
     *  'brochureMarkup',
     *  'order['name','code','date']',
     * ]
     */
    public function remotePdfFilenameAction(Request $request) {
        $params = $request->request->all();

        $result = null;
        try {
            $result = InvestmentSummaryHelper::getOutputFromHtmlFileNameRemote($this->container, $params);
        } catch (Exception $e) {
            return $this->getErrorResponse($e->getMessage());
        }

        return $this->getSerializedResponse($result);
    }
}
