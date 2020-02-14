# Amazon Transcribe with Nexmo Voice and AWS S3 using PHP

[![Deploy](https://www.herokucdn.com/deploy/button.svg)](https://nexmo.dev/aws-nexmo-transcribe-php)

PHP example script to be used as a callback by Nexmo Voice to prompt a caller for a message. Then, after the message is received, download the MP3 file to be uploaded to AWS S3 prior to creating a Amazon Transcribe job to convert the contents to text.

For more detail see the accompanying blog post at: [https://www.nexmo.com/blog/2020/02/14/aws-transcribe-with-nexmo-voice-using-php](https://www.nexmo.com/blog/2020/02/14/aws-transcribe-with-nexmo-voice-using-php)

## Prerequisites
For this example app the following are needed:

* [PHP](https://www.php.net/) installed locally (version 7.3+ preferred)
* Composer installed [globally](https://getcomposer.org/doc/00-intro.md#globally)
* [Nexmo](https://www.nexmo.com/) account
* [AWS](https://aws.amazon.com/) account
* [ngrok](https://ngrok.io) installed locally (more details later)

## AWS Services

For this example we will use multiple AWS services, such as [AWS S3](https://aws.amazon.com/s3/) and [Amazon Transcribe](https://aws.amazon.com/transcribe/).

In S3, create a bucket that will be used to store the MP3 files retrieved from Nexmo. Then make note of the bucket name for later inclusion to the `.env` file.

Using the [AWS IAM](https://aws.amazon.com/iam/), add a new user and add the following permissions by importing the following JSON.

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "VisualEditor0",
            "Effect": "Allow",
            "Action": "transcribe:*",
            "Resource": "*"
        },
        {
            "Sid": "VisualEditor1",
            "Effect": "Allow",
            "Action": [
            "s3:PutObject",
            "s3:GetObject",
            "s3:DeleteObject"
        ],
            "Resource": "arn:aws:s3:::{bucket_name}/*"
        }
    ]
}
```

## Running the App

This sample app uses a `.env` file to provide the API key and URL to the IBM Tone Analyzer API.

Copy the provided `.env.example` file to a new file called `.env`:

```
cp .env.example .env
```

Then update the values as needed from AWS and Nexmo, and save.

```
APP_ID=voice-aws-transcribe-php
LANG_CODE=en-US
SAMPLE_RATE=8000
AWS_REGION=us-east-1
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_S3_BUCKET_NAME=
AWS_S3_RECORDING_FOLDER_NAME=
NEXMO_APPLICATION_PRIVATE_KEY_PATH=
NEXMO_APPLICATION_ID=
```

### Use Composer to install dependencies

Both methods of serving the app, shown below, do require the use of Composer to install dependencies and set up the autoloader.

Assuming a Composer global installation. [https://getcomposer.org/doc/00-intro.md#globally](https://getcomposer.org/doc/00-intro.md#globally)

```
composer install
```

#### Launching the PHP built-in webserver

We will utilize the PHP built-in webserver for this sample application. Though it not intended for production, it is fine for sample/test usage. To serve the app run the following command in a terminal from the application directory:

```
php -S localhost:8080
```

## Launch ngrok
To enable the Nexmo callback to reach the application, expose the application to the internet using [ngrok](https://ngrok.com/). To see how, [check out this guide](https://www.nexmo.com/blog/2017/07/04/local-development-nexmo-ngrok-tunnel-dr/).

## Linking the app to Nexmo

After buying a virtual phone number through the [Nexmo Dashboard](https://dashboard.nexmo.com/buy-numbers), create an application with Voice capabilities and link it to the number. Make sure to generate the public/private keys, and save the private key in the application root when prompted.

As part of the Application creation, we also need to add the URLs for Event and Answer callbacks, while Fallback is not needed for this example.

These URLs will need the ngrok addresses provided after launching ngrok via CLI.

For example:
```
    Event URL = https://567d01f9.ngrok.io/webhooks/event
    Answer URL = https://567d01f9.ngrok.io/webhooks/answer
```

## Try it out

With the example PHP application running using the PHP built-in webserver, and made available online for the callbacks with ngrok, make a phone call to the Nexmo virtual number and follow the prompts.

After leaving a message, there should be an MP3 file added to the [bucket in S3](https://s3.console.aws.amazon.com/s3/) as well as a [transcribe job running in Amazon Transcribe](https://console.aws.amazon.com/transcribe/).

Upon successful transcription the final text can be downloaded, or used in applications that can can connect and download it.

## Blog Post

For a more full explanation and example, check out the [blog post](https://www.nexmo.com/blog).
