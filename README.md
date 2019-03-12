# Search Replace DB

This script was made to aid the process of migrating PHP and MySQL based websites. It has additional features for WordPress and Drupal but works for most other similar CMSes.

If you find a problem let us know in the issues area and if you can improve the code then please fork the repository and send us a pull request :)

## Usage

1. *Do backups.*
2. Migrate all your website files.

### CLI script

To invoke the script, navigate in your shell to the directory to where you installed Search Replace DB.

Type `php srdb.cli.php` to run the program. Type `php srdb.cli.php --help` for usage information:

	-h, --host
	
		Required. The hostname of the database server.
		
	-n, --name
	
		Required. Database name.
		
	-u, --user
	
		Required. Database user.
		
	-p, --pass
	
		Required. Database user's password.
		
	--port
	
		Optional. Port on database server to connect to.
		The default is 3306. (MySQL default port).
		
	-s, --search
	
		String to search for or `preg_replace()` style regular
		expression.
		
	-r, --replace
	
		None empty string to replace search with or
		`preg_replace()` style replacement.
		
	-t, --tables
	
		If set only runs the script on the specified table, comma
		separate for multiple values.
		
	-i, --include-cols
	
		If set only runs the script on the specified columns, comma
		separate for multiple values.
		
	-x, --exclude-cols
	
		If set excludes the specified columns, comma separate for
		multiple values.
		
	-g, --regex [no value]
	
		Treats value for -s or --search as a regular expression and
		-r or --replace as a regular expression replacement.
		
	-l, --pagesize
	
		How many rows to fetch at a time from a table.
		
	-z, --dry-run [no value]
	
		Prevents any updates happening so you can preview the number
		of changes to be made
		
	-e, --alter-engine
	
		Changes the database table to the specified database engine
		eg. InnoDB or MyISAM. If specified search/replace arguments
		are ignored. They will not be run simultaneously.
		
	-a, --alter-collation
	
		Changes the database table to the specified collation
		eg. utf8_unicode_ci. If specified search/replace arguments
		are ignored. They will not be run simultaneously.
		
	-v, --verbose [true|false]
	
		Defaults to true, can be set to false to run script silently.
		
	--help
	
		Displays this help message ;)

	--dry-run
	
		output what should have been updated, no updates


