<?php

require('vendor/autoload.php');

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Nexmo\Client;
use Nexmo\Client\Credentials\Keypair;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use Aws\TranscribeService\TranscribeServiceClient;

Dotenv\Dotenv::create(__DIR__)->load();

$app = AppFactory::create();

/**
 * Answer the call and prompt using NCCO flow
 */
$app->get('/webhooks/answer', function (Request $request, Response $response) {
    $uri = $request->getUri();

    // NOTE: The usage of $uri below will not work when using ngrok, as getHost() will return localhost
    $ncco = [
        [
            'action' => 'talk',
            'text' => 'Please leave a message after the tone, then press #.'
        ],
        [
            'action' => 'record',
            'eventUrl' => [
                $uri->getScheme().'://'.$uri->getHost().':'.$uri->getPort().'/webhooks/fetch'
            ],
            'endOnSilence' => '3',
            'endOnKey' => '#',
            'beepOnStart' => true
        ],
        [
            'action' => 'talk',
            'text' => 'Thank you for your message. Goodbye.'
        ],
        [
            'action' => 'notify',
            'payload' => ['foo'=>'bar'],
            'eventUrl' => [
                $uri->getScheme().'://'.$uri->getHost().':'.$uri->getPort().'/webhooks/transcribe'
            ],
            'eventMethod' => "POST"
        ],

    ];

    $response->getBody()->write(json_encode($ncco));
    return $response
        ->withHeader('Content-Type', 'application/json');
});

/**
 * Event URL used by Nexmo for update POSTs
 */
$app->post('/webhooks/event', function (Request $request, Response $response) {

    $params = $request->getParsedBody();

    error_log($params['recording_url']);

    return $response
        ->withStatus(204);
});

/**
 * Fetch the voice recording from Nexmo, store on AWS S3
 */
$app->post('/webhooks/fetch', function (Request $request, Response $response) {

    $params = json_decode($request->getBody(), true);

    // Create Nexmo Client
    $keypair = new Keypair(
        file_get_contents($_ENV['NEXMO_APPLICATION_PRIVATE_KEY_PATH']),
        $_ENV['NEXMO_APPLICATION_ID']
    );

    $nexmoClient = new Client($keypair);

    $data = $nexmoClient->get($params['recording_url']);

    // Create AWS S3 Client
    $S3Client = new S3Client([
        'credentials' => [
            'key'    => $_ENV['AWS_ACCESS_KEY_ID'],
            'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
        ],
        'region' => $_ENV['AWS_REGION'],
        'version' => 'latest',
    ]);

    $adapter = new AwsS3Adapter($S3Client, $_ENV['AWS_S3_BUCKET_NAME']);

    $filesystem = new Filesystem($adapter);

    $filesystem->put('/' . $_ENV['AWS_S3_RECORDING_FOLDER_NAME'] .'/'.$params['conversation_uuid'].'.mp3', $data->getBody());

    return $response
        ->withStatus(204);
});

/**
 * Request Amazon Transcription of the voice file
 */
$app->post('/webhooks/transcribe', function (Request $request, Response $response) {

    $params = json_decode($request->getBody(), true);

    // Create Amazon Transcribe Client
    $awsTranscribeClient = new TranscribeServiceClient([
        'credentials' => [
            'key'    => $_ENV['AWS_ACCESS_KEY_ID'],
            'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
        ],
        'region' => $_ENV['AWS_REGION'],
        'version' => 'latest',
    ]);

    $transcriptionResult = $awsTranscribeClient->startTranscriptionJob([
            'LanguageCode' => 'en-US',
            'Media' => [
                'MediaFileUri' => 'https://' . $_ENV['AWS_S3_BUCKET_NAME'] . '.s3.amazonaws.com/' . $_ENV['AWS_S3_RECORDING_FOLDER_NAME'] . '/' . $params['conversation_uuid'] . '.mp3',
            ],
            'MediaFormat' => 'mp3',
            'TranscriptionJobName' => 'nexmo_voice_' . $params['conversation_uuid'],
    ]);

    $response->getBody()->write(json_encode($transcriptionResult->toArray()));

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(204);
});

$app->run();
