<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit5bae2998433e5d3f0359625108260b13
{
    public static $prefixLengthsPsr4 = array (
        'L' => 
        array (
            'LINE\\' => 5,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'LINE\\' => 
        array (
            0 => __DIR__ . '/..' . '/linecorp/line-bot-sdk/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit5bae2998433e5d3f0359625108260b13::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit5bae2998433e5d3f0359625108260b13::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
