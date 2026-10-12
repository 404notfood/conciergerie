<?php
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
class CreateAppAdmin extends Command {
    protected $signature='app:create-admin {email} {--name=Administrateur}';
    protected $description='Créer un compte individuel d’administration globale sans modifier un compte existant.';
    public function handle(): int {
        $data=['email'=>$this->argument('email'),'name'=>$this->option('name')];$validator=Validator::make($data,['email'=>['required','email','unique:users,email'],'name'=>['required','string','max:120']]);
        if($validator->fails()){$this->error($validator->errors()->first());return self::FAILURE;}
        $password=Str::password(20);$user=User::create([...$data,'password'=>$password]);$user->forceFill(['is_admin'=>true,'email_verified_at'=>now()])->save();$this->info('Administrateur : '.$user->email);$this->info('Mot de passe initial : '.$password);return self::SUCCESS;
    }
}
