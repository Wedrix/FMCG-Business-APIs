<?php

namespace App\TextMessages;

use App\Models\Receipt;
use App\SMS\Textable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class NewSaleTextMessage extends Textable implements ShouldQueue
{
    use Queueable, SerializesModels;

    private Receipt $receipt;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Receipt $receipt)
    {
        $this->receipt = $receipt;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('Storekd Inc') // TODO: Change to Egen Gen after approval
                    ->to($this->receipt->customer_phone)
                    ->message(
                        "Hello ".$this->receipt->customer_name.",\n".
                        "Thank you for purchasing at Eben Genesis.\n".
                        "Your receipt number is: #0000{$this->receipt->id}."
                    );
    }
}
