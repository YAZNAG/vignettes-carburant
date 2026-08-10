<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TwoFactorRecoveryCode;
use App\Services\AuditService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly AuditService $audit,
    ) {}

    /**
     * Démarre l'enrôlement : génère un secret et le QR code (SVG).
     * Le secret n'est actif qu'après confirmation par un premier code valide.
     */
    public function enroler(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->totpActive()) {
            return response()->json([
                'message' => 'La double authentification est déjà activée.',
            ], 409);
        }

        $secret = $this->google2fa->generateSecretKey(32);
        $user->forceFill([
            'totp_secret' => $secret,
            'totp_confirme_at' => null,
        ])->saveQuietly();

        $uri = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        $svg = (new Writer(
            new ImageRenderer(new RendererStyle(220), new SvgImageBackEnd),
        ))->writeString($uri);

        return response()->json([
            'secret' => $secret,
            'otpauth_uri' => $uri,
            'qr_svg' => $svg,
        ]);
    }

    /**
     * Confirme l'enrôlement avec un premier code valide,
     * et délivre les 8 codes de secours (affichés une seule fois).
     */
    public function confirmer(Request $request): JsonResponse
    {
        $donnees = $request->validate(['code' => ['required', 'string', 'max:10']]);
        $user = $request->user();

        if (! $user->totp_secret) {
            return response()->json(['message' => 'Aucun enrôlement en cours.'], 409);
        }

        if (! $this->google2fa->verifyKey($user->totp_secret, preg_replace('/\s+/', '', $donnees['code']))) {
            throw ValidationException::withMessages(['code' => 'Code de vérification incorrect.']);
        }

        $user->forceFill(['totp_confirme_at' => now()])->saveQuietly();
        $codes = $this->genererCodesSecours($user->id);

        $this->audit->enregistrer('activation_2fa', userId: $user->id);

        return response()->json([
            'message' => 'Double authentification activée.',
            'codes_secours' => $codes,
        ]);
    }

    /**
     * Désactivation : mot de passe + code exigés.
     * Refusée si le rôle impose la 2FA.
     */
    public function desactiver(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'string', 'max:20'],
        ]);
        $user = $request->user();

        if ($user->totpObligatoire()) {
            return response()->json([
                'message' => 'La double authentification est obligatoire pour votre rôle.',
            ], 403);
        }

        if (! Hash::check($donnees['password'], $user->password)
            || ! $this->google2fa->verifyKey((string) $user->totp_secret, preg_replace('/\s+/', '', $donnees['code']))) {
            throw ValidationException::withMessages(['code' => 'Vérification impossible : mot de passe ou code incorrect.']);
        }

        $user->forceFill([
            'totp_secret' => null,
            'totp_confirme_at' => null,
        ])->saveQuietly();
        $user->codesSecours()->delete();

        $this->audit->enregistrer('desactivation_2fa', userId: $user->id);

        return response()->json(['message' => 'Double authentification désactivée.']);
    }

    /** Régénère les codes de secours (les anciens sont invalidés). */
    public function regenererCodesSecours(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->totpActive()) {
            return response()->json(['message' => 'La double authentification n\'est pas activée.'], 409);
        }

        $codes = $this->genererCodesSecours($user->id);
        $this->audit->enregistrer('regeneration_codes_secours', userId: $user->id);

        return response()->json(['codes_secours' => $codes]);
    }

    /** @return string[] les 8 codes en clair (seule et unique restitution) */
    private function genererCodesSecours(int $userId): array
    {
        TwoFactorRecoveryCode::where('user_id', $userId)->delete();

        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $clair = Str::upper(Str::random(4).'-'.Str::random(4));
            $codes[] = $clair;
            TwoFactorRecoveryCode::create([
                'user_id' => $userId,
                'code' => Hash::make($clair),
                'created_at' => now(),
            ]);
        }

        return $codes;
    }
}
