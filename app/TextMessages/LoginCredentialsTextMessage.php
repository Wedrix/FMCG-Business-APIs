<?php

namespace App\TextMessages;

use App\Models\User;
use App\SMS\Textable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class LoginCredentialsTextMessage extends Textable implements ShouldQueue
{
    use Queueable, SerializesModels;

    private User $user;

    private string $password;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user, string $password)
    {
        $this->user = $user;

        $this->password = $password;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('Eben Gen')
                    ->to($this->user->phone_number)
                    ->message(
                        "Hello {$this->user->full_name}, \n".
                        "Kindly find your login credentials below: \n\n".
                        "Username: {$this->user->username} \n".
                        "Password: {$this->password}"
                    );
    }
}
