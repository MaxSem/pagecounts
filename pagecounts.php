<?php

ini_set( 'display_errors', 0 );

set_error_handler(
    function( $errno, $errstr, $errfile, $errline ) {
        err( "$errstr in $errfile line $errline" );
    }
);

$allWikis = dblist( 'all' );
$privateWikis = array_flip( dblist( 'private' ) );
list( $user, $password ) = getCredentials();

function dblist( $name ) {
    $list = file( "https://raw.githubusercontent.com/wikimedia/operations-mediawiki-config/master/dblists/$name.dblist" );
    return array_map( 'trim', $list );
}

function err( $msg ) {
    file_put_contents( __DIR__ . '/logs/log.log', "$msg\n", FILE_APPEND );
    file_put_contents( 'php://stderr', "$msg\n" );
    exit( 1 );
}

function getCredentials() {
    $conf = parse_ini_file( dirname( __DIR__ ) . '/replica.my.cnf' );
    return [ $conf['user'], $conf['password'] ];
}

function getCounts( $wiki ) {
    global $user, $password;

    $db = new mysqli( "$wiki.labsdb", $user, $password, "{$wiki}_p" );
    $sql = 'SELECT SUM(ss_total_pages) AS ss_total_pages, SUM(ss_good_articles) as ss_good_articles FROM site_stats';
    $res = $db->query( $sql );
    $row = $res->fetch_object();
    $db->close();

    return [
        'pages' => intval( $row->ss_total_pages ),
		'contentPages' => intval( $row->ss_good_articles ),
    ];
}

$stats = [];
foreach ( $allWikis as $wiki ) {
    if ( isset( $privateWikis[$wiki] ) || $wiki == 'labswiki' || $wiki == 'labtestwiki' ) {
        continue;
    }
    $stats[$wiki] = getCounts( $wiki );
}

echo json_encode( $stats );
