<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Component;

use AzerioidPanel\Broker\BrokerException;
use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Database\BrokerConfigWriter;
use AzerioidPanel\Broker\Runtime;
use AzerioidPanel\Broker\Secrets;

final class MongoProvisioner
{
    private const ADMIN_USER = 'azerioid_panel_admin';

    public function __construct(
        private readonly Config $config,
        private readonly Runtime $runtime,
    ) {
    }

    public function provision(OperationLogger $log): void
    {
        $password = Secrets::generatePassword();
        $configPath = getenv('AZERIOID_PANEL_CONFIG') ?: getenv('LACMP_PANEL_CONFIG') ?: '/etc/azerioid-panel/broker.json';
        $confPath = $this->mongodConfPath();
        if ($confPath !== null) {
            $log->info("Securing MongoDB bind address in {$confPath}");
            $content = $this->runtime->readFile($confPath);
            if (preg_match('/^\s*bindIp\s*:/m', $content) === 1) {
                $content = preg_replace('/^\s*bindIp\s*:.*/m', '  bindIp: 127.0.0.1', $content) ?? $content;
            } else {
                $content .= "\nnet:\n  bindIp: 127.0.0.1\n";
            }
            $this->runtime->writeFile($confPath, $content, 0644);
            $this->runtime->exec(['/usr/bin/systemctl', 'restart', 'mongod'], null, 120);
        }

        $escaped = str_replace("'", "\\'", $password);
        $createUser = "db.getSiblingDB('admin').createUser({user: '" . self::ADMIN_USER . "', pwd: '{$escaped}', roles: [{role: 'root', db: 'admin'}]});";
        $log->info('Creating MongoDB panel admin user.');
        $result = $this->runtime->exec(['/usr/bin/mongosh', '--quiet', '--eval', $createUser], null, 120);
        if (!$result->ok()) {
            $result = $this->runtime->exec(['/usr/bin/mongo', '--quiet', '--eval', $createUser], null, 120);
        }
        if (!$result->ok()) {
            throw new BrokerException('Failed to create MongoDB admin user.', 1);
        }

        if ($confPath !== null) {
            $content = $this->runtime->readFile($confPath);
            if (preg_match('/^\s*authorization\s*:/m', $content) === 1) {
                $content = preg_replace('/^\s*authorization\s*:.*/m', '  authorization: enabled', $content) ?? $content;
            } elseif (preg_match('/^security\s*:/m', $content) === 1) {
                $content = preg_replace('/^security\s*:/m', "security:\n  authorization: enabled", $content) ?? $content;
            } else {
                $content .= "\nsecurity:\n  authorization: enabled\n";
            }
            $this->runtime->writeFile($confPath, $content, 0644);
            $this->runtime->exec(['/usr/bin/systemctl', 'restart', 'mongod'], null, 120);
        }

        BrokerConfigWriter::merge($this->runtime, $configPath, [
            'mongodb' => [
                'host' => '127.0.0.1',
                'port' => 27017,
                'user' => self::ADMIN_USER,
                'password' => $password,
            ],
        ]);
        $log->info('MongoDB secured (localhost + auth). SSPL license applies to mongodb-org packages.');
    }

    private function mongodConfPath(): ?string
    {
        foreach (['/etc/mongod.conf', '/etc/mongodb.conf'] as $path) {
            if ($this->runtime->fileExists($path)) {
                return $path;
            }
        }

        return null;
    }
}
