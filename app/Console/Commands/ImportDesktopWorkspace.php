<?php

namespace App\Console\Commands;

use App\Services\Desktop\WorkspaceSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Crypt, DB, Schema};

class ImportDesktopWorkspace extends Command
{
    protected $signature = 'desktop:import-workspace';
    protected $description = 'Carga una copia de escritorio en una base SQLite nueva';

    public function handle(): int
    {
        $database = config('database.connections.sqlite.database');
        if (getenv('MICV_IMPORT_WORKSPACE') !== '1' || DB::getDefaultConnection() !== 'sqlite'
            || !is_string($database) || !is_file($database) || is_link($database)
            || filesize($database) !== 0) {
            $this->error('La importación requiere una base SQLite nueva y vacía.');
            return self::FAILURE;
        }

        try {
            $snapshot = json_decode(stream_get_contents(STDIN, 40 * 1024 * 1024), true, flags: JSON_THROW_ON_ERROR);
            $tables = $snapshot['tables'];
            if ($snapshot['format'] !== 'micv-workspace-v1' || $snapshot['state'] !== 'staged'
                || array_diff(array_keys($tables), WorkspaceSnapshot::TABLES)
                || array_diff(WorkspaceSnapshot::TABLES, array_keys($tables))) {
                throw new \RuntimeException('Formato de copia inválido.');
            }
            if ($this->callSilent('migrate', ['--force' => true]) !== self::SUCCESS) {
                throw new \RuntimeException('Error de migración.');
            }
            DB::transaction(function () use ($snapshot, $tables) {
                DB::statement('PRAGMA defer_foreign_keys = ON');
                $this->importRows($tables);
                \App\Services\Desktop\LocalClientJournal::initialize($tables);
                \App\Services\Desktop\LocalBusinessJournal::initialize();
                $actor = DB::table('users')->find($snapshot['user_id']);
                if (!$actor || !$actor->activo || $actor->rol !== $snapshot['role']
                    || ($actor->rol === 'cyber' && (int) $actor->cyber_id !== $snapshot['tenant_id'])
                    || DB::select('PRAGMA foreign_key_check')) {
                    throw new \RuntimeException('La copia no tiene una cuenta o referencias válidas.');
                }
            });
        } catch (\Throwable) {
            $this->error('No se pudo preparar SQLite. Revise que el paquete y el servidor usen el mismo esquema.');
            return self::FAILURE;
        }
        return self::SUCCESS;
    }

    private function importRows(array $tables): void
    {
        $ids = [];
        foreach ($tables as $table => $rows) {
            $key = $table === 'paises' ? 'codigo' : 'id';
            $ids[$table] = array_fill_keys(array_column($rows, $key), true);
        }
        $disabledPassword = password_hash(bin2hex(random_bytes(48)), PASSWORD_BCRYPT);
        $references = [];
        foreach ($tables as $table => $rows) {
            DB::table($table)->delete();
            $columns = array_column(Schema::getColumns($table), null, 'name');
            $foreignKeys = Schema::getForeignKeys($table);
            foreach ($rows as $row) {
                if (array_diff_key($row, $columns)) {
                    throw new \RuntimeException('La copia tiene columnas desconocidas.');
                }
                foreach ($foreignKeys as $foreign) {
                    if (count($foreign['columns']) !== 1) {
                        throw new \RuntimeException('Referencia no admitida.');
                    }
                    $column = $foreign['columns'][0];
                    $parent = $foreign['foreign_table'];
                    $id = $row[$column] ?? null;
                    if ($id === null || isset($ids[$parent][$id])) {
                        continue;
                    }
                    if ($columns[$column]['nullable']) {
                        $row[$column] = null;
                    } elseif ($parent === 'users' && is_int($id) && $id > 0) {
                        $references[$id] = true;
                    } else {
                        throw new \RuntimeException('Falta una referencia obligatoria.');
                    }
                }
                if ($table === 'users') {
                    $row['password'] = $disabledPassword;
                    $row['remember_token'] = null;
                }
                $secret = match ($table) {
                    'correo_servicios' => 'clave_correo',
                    'jz_mensajes' => 'texto',
                    default => null,
                };
                if ($secret && isset($row[$secret])) {
                    $row[$secret] = Crypt::encryptString($row[$secret]);
                }
                DB::table($table)->insert($row);
            }
        }
        foreach (array_keys($references) as $id) {
            $ref = bin2hex(random_bytes(12));
            DB::table('users')->insert([
                'id' => $id, 'usuario' => 'ref_'.$ref, 'email' => $ref.'@local.invalid',
                'name' => 'Usuario de referencia', 'rol' => 'cliente', 'activo' => false,
                'password' => $disabledPassword,
            ]);
        }
    }
}
