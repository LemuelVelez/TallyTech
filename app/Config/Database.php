<?php

namespace Config;

use CodeIgniter\Database\Config;

/**
 * Database Configuration
 */
class Database extends Config
{
    /**
     * The directory that holds the Migrations and Seeds directories.
     */
    public string $filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;

    /**
     * Lets you choose which connection group to use if no other is specified.
     */
    public string $defaultGroup = 'default';

    /**
     * The default database connection.
     *
     * @var array<string, mixed>
     */
    public array $default = [
        'DSN'          => '',
        'hostname'     => 'localhost',
        'username'     => 'root',
        'password'     => '',
        'database'     => 'tallytech',
        'DBDriver'     => 'MySQLi',
        'DBPrefix'     => '',
        'pConnect'     => false,
        'DBDebug'      => true,
        'charset'      => 'utf8mb4',
        'DBCollat'     => 'utf8mb4_general_ci',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => true,
        'failover'     => [],
        'port'         => 3306,
        'numberNative' => false,
        'foundRows'    => false,
        'dateFormat'   => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    //    /**
    //     * Sample database connection for SQLite3.
    //     *
    //     * @var array<string, mixed>
    //     */
    //    public array $default = [
    //        'database'    => 'database.db',
    //        'DBDriver'    => 'SQLite3',
    //        'DBPrefix'    => '',
    //        'DBDebug'     => true,
    //        'swapPre'     => '',
    //        'failover'    => [],
    //        'foreignKeys' => true,
    //        'busyTimeout' => 1000,
    //        'synchronous' => null,
    //        'dateFormat'  => [
    //            'date'     => 'Y-m-d',
    //            'datetime' => 'Y-m-d H:i:s',
    //            'time'     => 'H:i:s',
    //        ],
    //    ];

    //    /**
    //     * Sample database connection for Postgre.
    //     *
    //     * @var array<string, mixed>
    //     */
    //    public array $default = [
    //        'DSN'        => '',
    //        'hostname'   => 'localhost',
    //        'username'   => 'root',
    //        'password'   => 'root',
    //        'database'   => 'ci4',
    //        'schema'     => 'public',
    //        'DBDriver'   => 'Postgre',
    //        'DBPrefix'   => '',
    //        'pConnect'   => false,
    //        'DBDebug'    => true,
    //        'charset'    => 'utf8',
    //        'swapPre'    => '',
    //        'failover'   => [],
    //        'port'       => 5432,
    //        'dateFormat' => [
    //            'date'     => 'Y-m-d',
    //            'datetime' => 'Y-m-d H:i:s',
    //            'time'     => 'H:i:s',
    //        ],
    //    ];

    //    /**
    //     * Sample database connection for SQLSRV.
    //     *
    //     * @var array<string, mixed>
    //     */
    //    public array $default = [
    //        'DSN'        => '',
    //        'hostname'   => 'localhost',
    //        'username'   => 'root',
    //        'password'   => 'root',
    //        'database'   => 'ci4',
    //        'schema'     => 'dbo',
    //        'DBDriver'   => 'SQLSRV',
    //        'DBPrefix'   => '',
    //        'pConnect'   => false,
    //        'DBDebug'    => true,
    //        'charset'    => 'utf8',
    //        'swapPre'    => '',
    //        'encrypt'    => false,
    //        'failover'   => [],
    //        'port'       => 1433,
    //        'dateFormat' => [
    //            'date'     => 'Y-m-d',
    //            'datetime' => 'Y-m-d H:i:s',
    //            'time'     => 'H:i:s',
    //        ],
    //    ];

    //    /**
    //     * Sample database connection for OCI8.
    //     *
    //     * You may need the following environment variables:
    //     *   NLS_LANG                = 'AMERICAN_AMERICA.UTF8'
    //     *   NLS_DATE_FORMAT         = 'YYYY-MM-DD HH24:MI:SS'
    //     *   NLS_TIMESTAMP_FORMAT    = 'YYYY-MM-DD HH24:MI:SS'
    //     *   NLS_TIMESTAMP_TZ_FORMAT = 'YYYY-MM-DD HH24:MI:SS'
    //     *
    //     * @var array<string, mixed>
    //     */
    //    public array $default = [
    //        'DSN'        => 'localhost:1521/FREEPDB1',
    //        'username'   => 'root',
    //        'password'   => 'root',
    //        'DBDriver'   => 'OCI8',
    //        'DBPrefix'   => '',
    //        'pConnect'   => false,
    //        'DBDebug'    => true,
    //        'charset'    => 'AL32UTF8',
    //        'swapPre'    => '',
    //        'failover'   => [],
    //        'dateFormat' => [
    //            'date'     => 'Y-m-d',
    //            'datetime' => 'Y-m-d H:i:s',
    //            'time'     => 'H:i:s',
    //        ],
    //    ];

    /**
     * This database connection is used when running PHPUnit database tests.
     *
     * @var array<string, mixed>
     */
    public array $tests = [
        'DSN'         => '',
        'hostname'    => '127.0.0.1',
        'username'    => '',
        'password'    => '',
        'database'    => ':memory:',
        'DBDriver'    => 'SQLite3',
        'DBPrefix'    => 'db_',  // Needed to ensure we're working correctly with prefixes live. DO NOT REMOVE FOR CI DEVS
        'pConnect'    => false,
        'DBDebug'     => true,
        'charset'     => 'utf8',
        'DBCollat'    => '',
        'swapPre'     => '',
        'encrypt'     => false,
        'compress'    => false,
        'strictOn'    => true,
        'failover'    => [],
        'port'        => 3306,
        'foreignKeys' => true,
        'busyTimeout' => 1000,
        'synchronous' => null,
        'dateFormat'  => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    public function __construct()
    {
        parent::__construct();

        // Ensure that we always set the database group to 'tests' if
        // we are currently running an automated test suite, so that
        // we don't overwrite live data on accident.
        if (ENVIRONMENT === 'testing') {
            $this->defaultGroup = 'tests';
            return;
        }

        $this->applyRuntimeDatabaseConfiguration();
    }

    /**
     * Use database credentials supplied by the deployment environment.
     *
     * Hosting/runtime variables take precedence over the starter defaults,
     * followed by CodeIgniter-specific variables. This avoids
     * falling back to the hard-coded "tallytech" database when the hosting
     * provider grants the application user access to a different database name.
     */
    private function applyRuntimeDatabaseConfiguration(): void
    {
        $url = $this->parseDatabaseUrl($this->environmentValue([
            'MYSQL_URL',
            'DATABASE_URL',
            'DB_URL',
            'MYSQL_PUBLIC_URL',
        ]));

        $hostname = $this->environmentValue([
            'DB_HOST',
            'MYSQLHOST',
            'MYSQL_HOST',
        ]) ?? ($url['host'] ?? null) ?? $this->environmentValue([
            'database.default.hostname',
            'database_default_hostname',
        ]);

        $database = $this->environmentValue([
            'DB_DATABASE',
            'DB_NAME',
            'MYSQLDATABASE',
            'MYSQL_DATABASE',
        ]) ?? ($url['database'] ?? null) ?? $this->environmentValue([
            'database.default.database',
            'database_default_database',
        ]);

        $username = $this->environmentValue([
            'DB_USERNAME',
            'DB_USER',
            'MYSQLUSER',
            'MYSQL_USER',
        ]) ?? ($url['user'] ?? null) ?? $this->environmentValue([
            'database.default.username',
            'database_default_username',
        ]);

        $password = $this->environmentValue([
            'DB_PASSWORD',
            'MYSQLPASSWORD',
            'MYSQL_PASSWORD',
        ]) ?? ($url['pass'] ?? null) ?? $this->environmentValue([
            'database.default.password',
            'database_default_password',
        ]);

        $port = $this->environmentValue([
            'DB_PORT',
            'MYSQLPORT',
            'MYSQL_PORT',
        ]) ?? (isset($url['port']) ? (string) $url['port'] : null) ?? $this->environmentValue([
            'database.default.port',
            'database_default_port',
        ]);

        if ($hostname !== null) {
            $this->default['hostname'] = $hostname;
        }
        if ($database !== null) {
            $this->default['database'] = $database;
        }
        if ($username !== null) {
            $this->default['username'] = $username;
        }
        if ($password !== null) {
            $this->default['password'] = $password;
        }
        if ($port !== null && ctype_digit($port)) {
            $this->default['port'] = (int) $port;
        }
    }

    /**
     * @param list<string> $keys
     */
    private function environmentValue(array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = getenv($key);

            if ($value === false && array_key_exists($key, $_ENV)) {
                $value = $_ENV[$key];
            }
            if ($value === false && array_key_exists($key, $_SERVER)) {
                $value = $_SERVER[$key];
            }
            if ($value === false || ! is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array{host?: string, port?: int, user?: string, pass?: string, database?: string}
     */
    private function parseDatabaseUrl(?string $url): array
    {
        if ($url === null || $url === '') {
            return [];
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['host'])) {
            return [];
        }

        $config = ['host' => $parts['host']];

        if (isset($parts['port'])) {
            $config['port'] = (int) $parts['port'];
        }
        if (isset($parts['user'])) {
            $config['user'] = rawurldecode($parts['user']);
        }
        if (isset($parts['pass'])) {
            $config['pass'] = rawurldecode($parts['pass']);
        }
        if (isset($parts['path'])) {
            $database = rawurldecode(ltrim($parts['path'], '/'));
            if ($database !== '') {
                $config['database'] = $database;
            }
        }

        return $config;
    }
}
