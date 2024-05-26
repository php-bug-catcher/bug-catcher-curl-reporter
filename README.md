# Curl  exception catcher for [Bug Tracker](https://github.com/php-sentinel/bug-catcher)

## Setup

```
composer require php-sentinel/bug-catcher-curl-reporter
```

## Try it

```php
$curlReporter = new \BugCatcher\Reporter\CurlReporter(
    "https://YourBugTrackerInstance.com:8000",
    'projectName',
    true
);
try {
    throw new Exception("Test exception");
} catch (Exception $e) {
    $curlReporter->reportException($e);
}


```