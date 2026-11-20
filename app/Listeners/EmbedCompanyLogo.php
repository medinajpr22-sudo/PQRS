<?php

namespace App\Listeners;

use App\Models\Tenant;
use App\Support\MailBranding;
use Illuminate\Mail\Events\MessageSending;

/**
 * Adjunta el logo de la empresa dentro del correo (imagen «en línea») cuando
 * el encabezado lo pide como «cid:logo-empresa» (ver MailBranding).
 *
 * Se hace al enviar, y no en cada notificación, para que funcione con todos
 * los correos que usan el diseño de la plataforma (también los de Laravel,
 * como recuperar la contraseña de un funcionario).
 */
class EmbedCompanyLogo
{
    public function handle(MessageSending $event): void
    {
        $email = $event->message;
        $html = $email->getHtmlBody();
        $tenant = tenancy()->initialized ? tenant() : null;

        if (! is_string($html) || ! str_contains($html, 'cid:'.MailBranding::LOGO_CID) || ! $tenant instanceof Tenant) {
            return;
        }

        foreach ($email->getAttachments() as $part) {
            if ($part->getName() === MailBranding::LOGO_CID) {
                return;
            }
        }

        $logo = MailBranding::embeddableLogo($tenant);

        if ($logo !== null) {
            $email->embedFromPath($logo['path'], MailBranding::LOGO_CID, $logo['mime']);
        }
    }
}
