<?php

namespace XIV\EventListener;

use App\Exceptions\GeneralJsonException;
use App\Service\Redis\Redis;
use App\Service\ThirdParty\Discord\Discord;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use XIV\Utils\Environment;
use XIV\Utils\Random;

class ExceptionListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException'
        ];
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $ex = $event->getException();

        if (getenv('SITE_CONFIG_SHOW_ERRORS') == '1' && getenv('APP_ENV') == 'prod') {
            print_r([
                "#{$ex->getLine()} {$ex->getFile()}",
                $ex->getMessage(),
                $event->getException()->getTraceAsString()
            ]);
        }

        if (getenv('SITE_CONFIG_SHOW_ERRORS') == '1') {
            return null;
        }

        $path = $event->getRequest()->getPathInfo();
        $pi   = pathinfo($path);

        if (isset($pi['extension']) && strlen($pi['extension'] > 2)) {
            $event->setResponse(new Response("File not found: ". $path, 404));
            return null;
        }
        
        $file = str_ireplace('/home/dalamud/dalamud', '', $ex->getFile());
        $file = str_ireplace('/home/dalamud/dalamud_staging', '', $file);
        $message = $ex->getMessage() ?: '(no-exception-message)';

        $json = (Object)[
            'Error'   => true,
            'Subject' => 'XIVAPI Service Error',
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
                'Note'    => "Get on discord: https://discord.gg/MFFVHWC and complain to @Vekien :)",
                'Env'     => constant(Environment::CONSTANT),
            ]
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
        
        if ($ignore === false && Redis::Cache()->get("mb_error_{$json->Hash}") == null) {
            Redis::Cache()->set("mb_error_{$json->Hash}", true);
            Discord::mog()->sendMessage(
                '569968196455759907',
                "```". json_encode($json, JSON_PRETTY_PRINT) ."```"
            );
        }
    
        // ignore non JSON errors
        if (get_class($ex) !== GeneralJsonException::class) {
            return;
        }

        $response = new JsonResponse($json, $json->Debug->Code);
        $response->headers->set('Content-Type','application/json');
        $response->headers->set('Access-Control-Allow-Origin','*');
        $event->setResponse($response);
    }
}
