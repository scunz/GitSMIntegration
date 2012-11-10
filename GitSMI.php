<?php

$BASE_PATH				= "/var/lib/jenkins/home/SMIDB";
$SHA1_SUCCESS_DB_FILE	= "$BASE_PATH/SHA1.db";

$projects = array(
	'LibGit2' => array(
		'Repo' => array(
			'url' => 'git://github.com/libgit2/libgit2.git',
			'branch' => 'development'
		),
		'Builds' => array(
			'LibGit2_GCC',
			'LibGit2_Clang'
		)
	),
	'libGitWrap' => array(
		'Repo' => array(
			'url' => 'git@github.com:/macgitver/libGitWrap.git',
			'branch' => 'development'
		),
		'Builds' => array(
			'libGitWrap_Qt4_GCC',
			'libGitWrap_Qt4_Clang',
			'libGitWrap_Qt5_GCC'
		),
		'Submods' => array(
			'3rd/libgit2' => 'LibGit2'
		)
	),
	'libHeaven' => array(
		'Repo' => array(
			'url' => 'git@github.com:/macgitver/libHeaven.git',
			'branch' => 'development'
		),
		'Builds' => array(
			'libHeaven_Qt4_GCC',
			'libHeaven_Qt4_Clang',
			'libHeaven_Qt5_GCC'
		)
	),
	'libDiffViews' => array(
		'Repo' => array(
			'url' => 'git@github.com:/macgitver/libDiffViews.git',
			'branch' => 'development'
		),
		'Builds' => array(
			'libDiffViews_Qt4_GCC'
		)
	),
	'MacGitver' => array(
		'Repo' => array(
			'url' => 'git@github.com:/macgitver/macgitver.git',
			'branch' => 'development'
		),
		'Builds' => array(
			'MacGitver_Qt4_GCC',
			'MacGitver_Qt4_Clang',
			'MacGitver_Qt5'
		),
		'Submods' => array(
			'3rd/libGitWrap' => 'libGitWrap',
			'3rd/libHeaven' => 'libHeaven'
		)
	),
	'DiffViewer' => array(
		'Repo' => array(
			'url' => 'git@github.com:/macgitver/DiffViewer.git',
			'bracnh' => 'development'
		),
		'Builds' => array(
			'DiffViewer_Qt4_GCC'
		),
		'Submods' => array(
			'3rd/libHeaven' => 'libHeaven',
			'3rd/libDiffViews' => 'libDiffViews'
		)
	)
);

function speak( $s ) {
	echo "$s\n";
}

function murmur( $s ) {
	echo "$s\n";
}

function squeal( $s ) {
	echo "$s\n";
	exit( 1 );
}

function speakUsage() {
	global $argv;
	speak( "Usage:" );
	speak( "\t$argv[0] record_success <buildName> <SHA1>" );
	exit( -1 );
}

function isBuildValid( $build ) {
	global $projects;
	
	foreach( $projects as $prj )
		foreach( $prj['Builds'] as $definedBuild )
			if( $definedBuild == $build )
				return true;

	return false;
}

function &projectOfBuild( $build ) {
	global $projects;
	
	foreach( $projects as $prj )
		foreach( $prj['Builds'] as $definedBuild )
			if( $definedBuild == $build )
				return $prj;
}

function httpGet( $url ){
	global $BASE_PATH;

	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_URL, $url );
	curl_setopt( $ch, CURLOPT_HEADER, 0 );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, file( $BASE_PATH."/auth.txt" ) );
	
	$f = curl_exec( $ch );
	curl_close( $ch );
	
	print_r( $f );
	return $f;
}

function readSHA1db( $fileName ) {
	$SHA1DB = array();
	
	$cnt = 0;
	while( file_exists( "$fileName.lock" ) ) {
		usleep( 25000 );
		if( $cnt++ > 15 )
			squeal( "Cannot get lock to $fileName" );
	}

	$cnt = 0;
	while( !file_exists( "$fileName" ) ) {
		usleep( 2500 );
		if( $cnt++ > 5 )
			squeal( "Cannot get lock to $fileName" );
	}

	$fd = fopen( $fileName, "r" )
		or squeal( "Cannot read SHA1-Database" );

	while( ( $line = fgets( $fd ) ) != false ) {
		$SHA1 = substr( $line, 0, 40 );
		$Key = substr( $line, 41, strlen( $line ) - 42 );
		$SHA1DB[ $Key ] = $SHA1;
	}
	fclose( $fd );

	return $SHA1DB;
}

function writeSHA1db( $fileName, $db ) {
	$cnt = 0;
	while( file_exists( "$fileName.lock" ) ) {
		usleep( 25000 );
		if( $cnt++ > 15 )
			squeal( "Cannot get lock to $fileName" );
	}

	$fd = fopen( "$fileName.lock", "w" );
	foreach( $db as $Key => $SHA1 ) {
		fputs( $fd, "$SHA1 $Key\n" );
	}
	fclose( $fd );
	
	unlink( $fileName );
	rename( "$fileName.lock", $fileName );
}
	
function writeSuccessDb() {
	global $SHA1_SUCCESS_DB_FILE;
	global $last_success;

	writeSHA1db( $SHA1_SUCCESS_DB_FILE, $last_success );
}

function checkBuild( $build ) {
	if( !isBuildValid( $build ) ) {
		squeal( "GitSMI: Build $build is not registered." );
	}
}

function checkSHA1( $sha1 ) {
	if( strlen( $sha1 ) != 40 ) {
		squeal( "SHA1 must be 40 digits." );
	}
}

function cmdRecordSuccess( $build, $sha1 ) {
	global $last_success;
	global $projects;
	
	checkBuild( $build );
	checkSHA1( $sha1 );
	
	if( isset( $last_success[ $build ] ) ) {
		if( $last_success[ $build ] === $sha1 ) {
			murmur( "GitSMI: Will not update last success of build $build to $sha1\n".
					"GitSMI: Because that SHA1 is already recorded" );
			$did_update = false;
		}
		else {
			$last_success[ $build ] = $sha1;
			speak( "GitSMI: Updated last success of build $build to $sha1" );
			$did_update = true;
		}
	}
	else {
		$last_success[ $build ] = $sha1;
		speak( "GitSMI: Recording first success of build $build as $sha1" );
		$did_update = true;
	}

	if( $did_update ) {
		writeSuccessDb();
	}

	$p =& projectOfBuild( $build );
	foreach( $p[ 'Builds' ] as $b ) {
		if( !isset( $last_success[$b] ) ) {
			return;
		}
		if( $last_success[$b] !== $sha1 ) {
			return;
		}
	}

	speak( "GitSMI: All builds of project ${p['Name']} are now at $sha1" );
	
	foreach( $projects as $run => $sub ) {
		if( isset( $sub[ 'Submods' ] ) ) {
			foreach( $sub[ 'Submods' ] as $path => $lib ){
				if( $lib === $p['Name'] ){
					speak( "GitSMI: Triggering build: ${run}_GitSMI" );
					httpGet( "macgitver.org:8080/job/${run}_GitSMI/build?"
							."token=GitSMI&cause=Try+To+Integrate" );
				}
			}
		}
	}
}

function cmdStartBuild( $build ) {
	global $last_success;
	
	checkBuild( $build );
	if( isset( $last_success[ $build ] ) ) {
		unset( $last_success[ $build ] );
		writeSuccessDb();
		speak( "GitSMI: Cleared last success of build $build" );
	}
}

function sniffForSubmodules( $src ) {
	$found = array();	
	$sms = array();
	exec( "cd $src && git submodule status", $sms );
	
	foreach( $sms as $sm ){
		$sha1 = substr( $sm, 1, 40 );
		$i = strpos( $sm, " ", 42 );
		$path = substr( $sm, 42, $i - 42 );
		$found[ $path ] = $sha1;
	}
	
	return $found;
}

function cmdIntegrate() {
	global $projects;
	global $last_success;
	global $BASE_PATH;

	$jobname = getenv( "JOB_NAME" );
	$srctree = getenv( "WORKSPACE" )."/src";
	$prjname = substr( $jobname, 0, strlen( $jobname ) - 7 );
	//checkBuild( $prjname );

	$prj = $projects[ $prjname ];
	
	$subs = sniffForSubmodules( $srctree );
	
	$fd = fopen( "$BASE_PATH/$jobname.ToDo", "w" );

	foreach( $prj[ 'Submods' ] as $path => $lib ){
		$lprj = $projects[ $lib ];
		$firstBuild = $lprj['Builds'][0];		
		$shaToIntegrate = $last_success[ $firstBuild ];		
		if( $shaToIntegrate !== $subs[ $path ] ){
			speak( "GitSMI: Will have to integrate $lib into $prjname" );
			fputs( $fd, "${subs[$path]} $path\n" );
			
			$url = $projects[$lib]['Repo']['url'];
			$cmd = "cd $srctree/$path && ".
				   "git fetch $url && ".
				   "git checkout -f $shaToIntegrate && ".
				   "git submodule update --init --recursive";

			exec( $cmd );
		}
	}
	
	fclose( $fd );
}

function cmdIntegrated() {
	global $projects;
	global $last_success;
	global $BASE_PATH;
	
	$jobname = getenv( "JOB_NAME" );
	$srctree = getenv( "WORKSPACE" )."/src";
	$prjname = substr( $jobname, 0, strlen( $jobname ) - 7 );
	//checkBuild( $prjname );
	
	$subs = sniffForSubmodules( $srctree );
	$prj = $projects[ $prjname ];
	
	$todos = readSHA1db( "$BASE_PATH/$jobname.ToDo" );
	
	$commitSubject = "Integration of:";
	$commitBody = "";
	$count = 0;
	foreach( $todos as $path => $fromSHA1 ){
		$toSHA1 = $subs[$path];
		$lib = $prj['Submods'][$path];
		
		$commitSubject .= " $lib";
		$commitBody .= "Updating $lib\n    From: $fromSHA1\n    To:   $toSHA1\n\n";
	
		$log = array();
		exec( "cd $srctree/$path && git shortlog $fromSHA1..$toSHA1", $log );
		$commitBody .= implode( "\n", $log );

		exec( "cd $srctree && git add $path" );
		$count++;
	}

	if( $count == 0 ) {
		return;
	}

	$commitBody = str_replace( "\"", "\\\"", $commitBody );

	$cmd = "cd $srctree && "
		 . "git commit "
		 .		"-m\"$commitSubject\n\n$commitBody\" "
		 .		"--author=\"Babbelbox's Jenkins <Jenkins@babbelbox.org>\" && "
		 . "git push origin development";

echo $cmd;

	exec( $cmd );
}

function requireParams( $n ) {
	global $argc;
	if( $argc < $n ) {
		speak( "$n parameters required" );
		speakUsage();
	}
}

function main() {
	global $SHA1_SUCCESS_DB_FILE;
	global $last_success;
	global $argv;
	global $projects;

	foreach( $projects as $n => $p ) {
		$projects[$n]['Name'] = $n;
	}

	$last_success = readSHA1db( $SHA1_SUCCESS_DB_FILE );

//	echo "LAST_SUCCESS-DB:\n";
//	var_dump( $last_success );
	
	requireParams( 2 );

	if( $argv[ 1 ] == "record_success" ) {
		requireParams( 4 );
		cmdRecordSuccess( $argv[2], $argv[3] );
		exit(0);
	}
	else if( $argv[ 1 ] == "start_build" ) {
		requireParams( 3 );
		cmdStartBuild( $argv[2] );
		exit(0);
	}
	else if( $argv[ 1 ] == "integrate" ) {
		cmdIntegrate();
		exit(0);
	}
	else if( $argv[ 1 ] == "integrated" ) {
		cmdIntegrated();
		exit(0);
	}
	else {
		squeal( "Bad command: $argv[1]" );
	}
}

main();

?>
