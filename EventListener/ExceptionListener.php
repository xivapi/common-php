<?php

namespace App\Common\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use App\Common\Constants\DiscordConstants;
use App\Common\Service\Redis\Redis;
use App\Common\ServicesThirdParty\Discord\Discord;
use App\Common\Utils\Environment;
use App\Common\Utils\Random;

class ExceptionListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException'
        ];
    }

    /**
     * Handle custom exceptions
     * @param GetResponseForExceptionEvent $event
     * @return null|void
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $ex = $event->getException();

        // if config enabled to show errors and app env is prod.
        if (getenv('SITE_CONFIG_SHOW_ERRORS') == '1' && getenv('APP_ENV') == 'prod') {
            print_r([
                "#{$ex->getLine()} {$ex->getFile()}",
                $ex->getMessage(),
                $event->getException()->getTraceAsString()
            ]);
        }

        // if we're showing errors, don't handle them (eg in dev mode)
        if (getenv('SITE_CONFIG_SHOW_ERRORS') == '1') {
            return null;
        }

        $path = $event->getRequest()->getPathInfo();
        $pi   = pathinfo($path);

        // if it's an image
        if (isset($pi['extension']) && strlen($pi['extension'] > 2)) {
            $event->setResponse(new Response("File not found: ". $path, 404));
            return null;
        }

        // remove root directories
        $file = str_ireplace('/home/dalamud/', '', $ex->getFile());
        $message = $ex->getMessage() ?: '(no-exception-message)';

        $json = (Object)[
            'Error'   => true,
            'Subject' => 'XIVAPI Service Error',
            'Note'    => "Get on discord: https://discord.gg/MFFVHWC and complain to @Vekien :)",
            'Message' => $message,
            'Hash'    => sha1($message),
            'Ex'      => get_class($ex),
            'Url'     => $event->getRequest()->getUri(),
            'Debug'   => (Object)[
                'ID'      => Random::randomHumanUniqueCode() . date('ymdh'),
                'File'    => "#{$ex->getLine()} {$file}",
                'Method'  => $event->getRequest()->getMethod(),
                'Path'    => $event->getRequest()->getPathInfo(),
                'Action'  => $event->getRequest()->attributes->get('_controller'),
                'Code'    => method_exists($ex, 'getStatusCode') ? $ex->getStatusCode() : 500,
                'Date'    => date('Y-m-d H:i:s'),
                'Env'     => constant(Environment::CONSTANT),
            ],
            'Trace' => $ex->getTraceAsString(),
        ];
        
        $ignore = false;
        
        // ignore 404 errors
        if ($json->Debug->Code == '404') {
            $ignore = true;
        }
        
        // ignore local
        if (stripos($json->Debug->File, 'vagrant') !== false) {
            $ignore = true;
        }

        // decide if to send a message to discord
        if ($ignore === false && Redis::Cache()->get("error_{$json->Hash}") == null) {
            Redis::Cache()->set("error_{$json->Hash}", true);
            Discord::mog()->sendMessage(DiscordConstants::ROOM_ERRORS, "```". json_encode($json, JSON_PRETTY_PRINT) ."```");
        }

        $response = new JsonResponse($json, $json->Debug->Code);
        $response->headers->set('Content-Type','application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Headers', '*');
        $event->setResponse($response);
    }
}
