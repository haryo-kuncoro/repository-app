<?php

namespace App\Controllers;

use App\Libraries\Captcha as CaptchaLib;

/** Endpoint AJAX untuk ceklis "Saya bukan robot" (driver builtin). */
class Captcha extends BaseController
{
    public function verify()
    {
        try {
            $payload = $this->request->getJSON(true);
        } catch (\Throwable) {
            $payload = null;
        }

        $result = (new CaptchaLib())->verifyInteraction(is_array($payload) ? $payload : []);

        return $this->response->setJSON($result);
    }
}
