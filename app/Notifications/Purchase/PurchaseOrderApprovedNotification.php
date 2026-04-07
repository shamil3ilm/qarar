<?php

declare(strict_types=1);

namespace App\Notifications\Purchase;

use App\Models\Purchase\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PurchaseOrderApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly PurchaseOrder $purchaseOrder) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Purchase Order #{$this->purchaseOrder->po_number} Approved")
            ->greeting("Hello {$notifiable->name},")
            ->line('Your purchase order has been approved.')
            ->line("**PO Number:** #{$this->purchaseOrder->po_number}")
            ->line("**Vendor:** {$this->purchaseOrder->vendor_name}")
            ->line("**Total:** {$this->purchaseOrder->currency_code} " . number_format((float) $this->purchaseOrder->total, 2))
            ->action('View Purchase Order', config('app.url') . '/purchase-orders/' . $this->purchaseOrder->id);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'purchase_order_approved',
            'po_id' => $this->purchaseOrder->id,
            'po_number' => $this->purchaseOrder->po_number,
            'vendor_name' => $this->purchaseOrder->vendor_name,
            'total' => $this->purchaseOrder->total,
            'currency' => $this->purchaseOrder->currency_code,
        ];
    }
}
