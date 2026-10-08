<?php

namespace App\Services;

use App\Models\Comercio;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommerceProfileService
{
    public function update(Request $request, Usuario $user): Comercio
    {
        abort_unless($user->tipo_usuario === 'comercio', 403, 'Solo los comercios pueden actualizar este perfil.');
        $commerce = Comercio::where('id_usuario', $user->id_usuario)->firstOrFail();
        $data = $request->validate([
            'id_usuario' => 'prohibited', 'id_comercio' => 'prohibited',
            'rif_identificacion' => 'prohibited', 'correo_contacto' => 'prohibited',
            'nombre_comercio' => 'prohibited', 'fecha_afiliacion' => 'prohibited',
            'telefono' => 'sometimes|nullable|string|max:20',
            'descripcion' => 'sometimes|nullable|string|max:1000',
            'sitio_web' => 'sometimes|nullable|url:http,https|max:255',
            'logo' => 'sometimes|nullable|url:http,https|max:255',
            'logo_url' => 'sometimes|nullable|url:http,https|max:255',
            'redes_sociales' => 'sometimes|nullable|array:facebook,instagram,twitter',
            'redes_sociales.*' => 'nullable|url:http,https|max:255',
            'facebook' => 'sometimes|nullable|url:http,https|max:255',
            'instagram' => 'sometimes|nullable|url:http,https|max:255',
            'twitter' => 'sometimes|nullable|url:http,https|max:255',
        ]);

        return DB::transaction(function () use ($commerce, $user, $data) {
            $profile = array_intersect_key($data, array_flip(['descripcion', 'sitio_web', 'logo', 'redes_sociales']));
            if (array_key_exists('logo_url', $data)) {
                $profile['logo'] = $data['logo_url'];
            }
            foreach (['facebook', 'instagram', 'twitter'] as $social) {
                if (array_key_exists($social, $data)) {
                    $profile['redes_sociales'] ??= $commerce->redes_sociales ?? [];
                    $profile['redes_sociales'][$social] = $data[$social];
                }
            }
            $commerce->update($profile);
            if (array_key_exists('telefono', $data)) {
                $user->update(['telefono' => $data['telefono']]);
            }

            return $commerce->fresh();
        });
    }
}
