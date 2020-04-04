# NestedFlowTracker

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![Build Status][ico-travis]][link-travis]
[![StyleCI][ico-styleci]][link-styleci]

This Laravel package allows metering the time spent from a start point to an end point in the code.

Take a look at [contributing.md](contributing.md) to see a to do list.

## Installation

Via Composer

``` bash
$ composer require adelinferaru/nestedflowtracker
```

## Configuration

**1. Publish the configuration file**

```> php artisan vendor:publish --provider="AdelinFeraru\NestedFlowTracker\NestedFlowTrackerServiceProvider" --tag="nestedflowtracker.config"```

**2. Publish migration files**

```> php artisan vendor:publish --provider="AdelinFeraru\NestedFlowTracker\NestedFlowTrackerServiceProvider" --tag="nestedflowtracker.migrations"```

**3. Setup environmental variables**

The package requires that you setup two environmental variables. Edit .env and add the following:

```FLOW_TRACKER_COMPONENT="default-component-name"```

```FLOW_TRACKER_DB_CONNECTION=default```

The **```FLOW_TRACKER_COMPONENT```** variable represents the name of the current application and when the package is used to track a user flow that spans multiple applications it is very usefull in understanding which one of the applicantions was reporting a specific tracking record.

The **```FLOW_TRACKER_DB_CONNECTION```** variable will specify the name of the database connection used to write the tracking records to. If you are not using the ```default```, you must define, in ```config/database.php```, a new DB connection to be used by NestedFlowTracker.

Example:
```$xslt
...
'nestedflowtracker' => [
            'driver' => 'mysql',
            'host' => env('FLOW_TRACKER_DB_HOST', '127.0.0.1'),
            'port' => env('FLOW_TRACKER_DB_PORT', '3306'),
            'database' => env('FLOW_TRACKER_DB_DATABASE', 'my_external_db'),
            'username' => env('FLOW_TRACKER_DB_USERNAME', 'root'),
            'password' => env('FLOW_TRACKER_DB_PASSWORD', 'secret'),
            'unix_socket' => env('FLOW_TRACKER_DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('FLOW_TRACKER_MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
...
```
Now in .env file you will have:

```FLOW_TRACKER_DB_CONNECTION=nestedflowtracker``` 


## Usage

Mostly you will be using 2 class static methods:

```$track_model = NestedFlowTracker::startTrack($timer_name, $message = null, array $setting = []);```

```NestedFlowTracker::endTrack($timer_name, array $setting = []);```

**$settings** array might contain the following:

- tracker_id : the unique identifier of the whole flow (spanning multiple apps) 
- user_id: the user_id of the user performing the flow or on whose behalf is the flow
- component: overwrite the default component/app name defined in the .env file when needed
- message: overwrite the message (usefull in ::endTask)
- context: specify a context
- result: pass in a result
- parent_id: programatically specify a the id of a parent tracker

**Note:** The ```NestedFlowTracker::startTrack``` method is returning a Model that is implementing the Nested Set.

**Note:** The startTrack / endTrack mark the begining and the end of SUB-FLOW. 
```$xslt
startTrack -------------------------> root:
    startTrack ---------------------> child 1
        startTrack -----------------> child 1.1
        endTrack
        
        startTrack -----------------> child 1.2
        endTrack
    endTrack

    startTrack ---------------------> child 2 
        startTrack -----------------> child 2.1
        endTrack
    endTrack
endTrack
    
```

When making an API call to another app that is part of the flow and might need to track its own internal flow, you can pass the following vars:

- ```"tracker_id" => NestedFlowTracker::getTrackerId(),```
- ```"tracker_parent_id" => $current_tracker->id,```




```$xslt
...
public static function doSomething($data) {
    $tracker_parent_id = $data['tracker_parent_id] ?? null;
    $tracker_id = $data['tracker_id] ?? NestedFlowTracker::getTrackerId();

    $doSomething_timer = "Meter my doSomething";
    $track = NestedFlowTracker::startTrack($doSomething_timer, 
                                           "Track my doSomething function", 
                                            [
                                                'context' => [],
                                                'user_id' => $data['user_id'],
            
                                                // "tracker_id" to be used only at the begining of a flow
                                                //'tracker_id' => $tracker_id,
            
                                                // "parent_id"  to be used only at the begining of a flow
                                                //'parent_id' => $tracker_parent_id
                                            ]);

    $result = [
        'success' => 1,
        'message' => null
    ];
    ...
    ...
    some code that the function does
    ...
    // Supposedly we'll call another function that we want to track as a child of the already existing tracker "$track"

    $processData_timer = "Meter my processData";
    $track2 = NestedFlowTracker::starTrack($processData_timer, "Process my data");
    
    // Call another function to do something with data     
    $new_data = $this->processData($data);
    
    NestedFlowTracker::endTrack($processData_timer);

    if($new_data) $result['success'] = 1;
    ...
    ...


    NestedFlowTracker::endTrack($doSomething_timer, ['result' => $result]);

    return $result;
}
...
```


## Change log

Please see the [changelog](changelog.md) for more information on what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [contributing.md](contributing.md) for details and a todolist.

## Security

If you discover any security related issues, please email alu@feenavigator.com instead of using the issue tracker.

## Credits

- [Feraru Ioan Adelin][link-author]
- [All Contributors][link-contributors]

## License

license. Please see the [license file](license.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/adelinferaru/nestedflowtracker.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/adelinferaru/nestedflowtracker.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/adelinferaru/nestedflowtracker/master.svg?style=flat-square
[ico-styleci]: https://styleci.io/repos/12345678/shield

[link-packagist]: https://packagist.org/packages/adelinferaru/nestedflowtracker
[link-downloads]: https://packagist.org/packages/adelinferaru/nestedflowtracker
[link-travis]: https://travis-ci.org/adelinferaru/nestedflowtracker
[link-styleci]: https://styleci.io/repos/12345678
[link-author]: https://github.com/adelinferaru
[link-contributors]: ../../contributors
