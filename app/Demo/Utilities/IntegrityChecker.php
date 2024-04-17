<?php

namespace App\Demo\Utilities;

class IntegrityChecker
{
    private $directory;
    private $hash_data_file;
    private $hash_input_file;
    private $hash_result_file;
    private $white_list = [
        'app',
        'bootstrap',
        'database',
        'node_modules',
        'public/index.php',
        'resources',
        'vendor',
        'artisan',
        'composer.json',
        'composer.lock',
        'package.json',
    ];

    public function __construct( $directory )
    {
        ini_set( 'max_execution_time', 300 );
        $this->directory        = $directory;
        $this->hash_input_file  = $this->absPath( 'hash.dat' );
        $this->hash_result_file = $this->absPath( 'hash.json' );
        $this->hash_data_file   = $this->absPath( 'hash' );
    }


    public function sign()
    {
        $this->createMd5DeepInputFile();

        $output = [];
        exec( 'md5deep -r -f ' . $this->hash_input_file, $output );

        $content = implode( PHP_EOL, $output );

        if ( file_exists( $this->hash_data_file ) )
        {
            unlink( $this->hash_data_file );
        }

        file_put_contents( $this->hash_data_file, $content );

        return true;
    }

    public function verify()
    {
        $this->createMd5DeepInputFile();

        $output = [];
        exec( 'md5deep -r -f ' . $this->hash_input_file . ' -x ' . $this->hash_data_file, $output );

        $files = [];
        foreach ( $output as $file )
        {
            $files[] = str_replace( $this->directory . '/', '', $file );
        }

        $result = [
            'timestamp'     => date( 'm/d/Y h:i:s a', time() ),
            'changed_files' => $files
        ];

        if ( file_exists( $this->hash_result_file ) )
        {
            unlink( $this->hash_result_file );
        }
        file_put_contents( $this->hash_result_file, json_encode( $result ) );

        return count( $output ) == 0;
    }

    public function results()
    {
        if ( ! file_exists( $this->hash_result_file ) )
        {
            return null;
        }

        $contents = file_get_contents( $this->hash_result_file );

        return json_decode( $contents );
    }

    public function isOkay()
    {
        $ic_results = $this->results();
        if ( ! is_null( $ic_results ) )
        {
            $changed_files_count = count( $ic_results->changed_files );

            return $changed_files_count == 0;
        }

        return false;
    }

    private function createMd5DeepInputFile()
    {
        $dirs = [];
        foreach ( $this->white_list as $relative_dir )
        {
            $dirs[] = $this->absPath( $relative_dir );
        }
        $content = implode( PHP_EOL, $dirs );

        file_put_contents( $this->hash_input_file, $content );
    }

    private function absPath( $relative_path )
    {
        return IntegrityChecker::joinPaths( $this->directory, $relative_path );
    }

    public static function joinPaths()
    {
        $paths = [];

        foreach ( func_get_args() as $arg )
        {
            if ( $arg !== '' )
            {
                $paths[] = $arg;
            }
        }

        return preg_replace( '#/+#', '/', join( '/', $paths ) );
    }
}
