<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit355067edffe0745f2f24270dd526719d
{
    public static $files = array (
        '0995b748b7f514caf1a7a0c897ecc8a2' => __DIR__ . '/..' . '/securetrading/stpp_json/src/helper.php',
    );

    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'Securetrading\\Stpp\\JsonInterface\\' => 33,
            'Securetrading\\Log\\' => 18,
            'Securetrading\\Loader\\' => 21,
            'Securetrading\\Ioc\\' => 18,
            'Securetrading\\Http\\' => 19,
            'Securetrading\\Data\\' => 19,
            'Securetrading\\' => 14,
        ),
        'P' => 
        array (
            'Psr\\Log\\' => 8,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Securetrading\\Stpp\\JsonInterface\\' => 
        array (
            0 => __DIR__ . '/..' . '/securetrading/stpp_json/src',
        ),
        'Securetrading\\Log\\' => 
        array (
            0 => __DIR__ . '/..' . '/securetrading/log/src',
        ),
        'Securetrading\\Loader\\' => 
        array (
            0 => __DIR__ . '/..' . '/securetrading/loader/src',
        ),
        'Securetrading\\Ioc\\' => 
        array (
            0 => __DIR__ . '/..' . '/securetrading/ioc/src',
        ),
        'Securetrading\\Http\\' => 
        array (
            0 => __DIR__ . '/..' . '/securetrading/http/src',
        ),
        'Securetrading\\Data\\' => 
        array (
            0 => __DIR__ . '/..' . '/securetrading/data/src',
        ),
        'Securetrading\\' => 
        array (
            0 => __DIR__ . '/..' . '/securetrading/exception/src',
        ),
        'Psr\\Log\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/log/Psr/Log',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit355067edffe0745f2f24270dd526719d::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit355067edffe0745f2f24270dd526719d::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit355067edffe0745f2f24270dd526719d::$classMap;

        }, null, ClassLoader::class);
    }
}
