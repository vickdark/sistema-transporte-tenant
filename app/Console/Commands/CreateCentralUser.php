<?php

namespace App\Console\Commands;

use App\Models\CentralUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Crea un administrador del panel central. Es la única forma de dar de alta el primero.
 */
class CreateCentralUser extends Command
{
    /**
     * @var string
     */
    protected $signature = 'central:create-user
                            {--name= : Nombre del administrador}
                            {--email= : Correo con el que ingresará}
                            {--password= : Contraseña (si se omite, se pide de forma interactiva)}';

    /**
     * @var string
     */
    protected $description = 'Crea un administrador del panel central';

    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?? $this->ask('Nombre'),
            'email' => $this->option('email') ?? $this->ask('Correo'),
            'password' => $this->option('password') ?? $this->secret('Contraseña'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:central_users,email'],
            'password' => ['required', 'string', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        CentralUser::create($validator->validated());

        $this->info("Administrador central {$data['email']} creado.");

        return self::SUCCESS;
    }
}
