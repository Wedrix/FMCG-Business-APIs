<?php

namespace App\TextMessages;

use App\Models\User;
use App\SMS\Textable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class LoginCredentials extends Textable implements ShouldQueue
{
    use Queueable, SerializesModels;

    private User $user;

    private string $tempPassword;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user, string $tempPassword)
    {
        $this->user = $user;

        $this->tempPassword = $tempPassword;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('Storekd Inc')
                    ->message(
                        "Hello ".$this->user->full_name.",\n".
                        "Your login password is: ".$this->tempPassword
                    );
    }
}
