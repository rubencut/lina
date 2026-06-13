<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Str;

class QrCodeController extends Controller
{
    public function index()
    {
        $viewer = request()->user();
        $query = User::with('classroom')->orderBy('name');

        if ($viewer->isStaffTeacherSupervisor()) {
            $query->whereHas('classroom', fn ($classroom) => $classroom->where('teacher_id', $viewer->id));
        } elseif ($viewer->isStudentEmployeeParticipant()) {
            $query->where('id', $viewer->id);
        }

        $users = $query->paginate(request('per_page', 50));
        $users->getCollection()->transform(function (User $user) {
            $user->qr_image = $user->qr_code ? self::dataUrlFor($user) : null;

            return $user;
        });

        return response()->json($users);
    }

    public function generateUserQr(User $user)
    {
        $this->ensureCanGenerateQr($user);

        if (! $user->qr_code) {
            $user->update(['qr_code' => Str::uuid()->toString()]);
            $this->audit('user.qr_generated', $user, null, ['qr_code' => $user->qr_code]);
        }

        return response()->json($this->qrPayload($user));
    }

    public function getQrCode(User $user)
    {
        $this->ensureCanViewUser($user);

        if (! $user->qr_code) {
            return response()->json(['error' => 'QR code not generated'], 404);
        }

        return response()->json($this->qrPayload($user));
    }

    public function downloadQrCode(User $user)
    {
        $this->ensureCanViewUser($user);

        if (! $user->qr_code) {
            return response()->json(['error' => 'QR code not generated'], 404);
        }

        return response()->streamDownload(function () use ($user) {
            echo $this->generateQrPng($user);
        }, "qr-code-{$user->id}.png", ['Content-Type' => 'image/png']);
    }

    public function printAllQrCodes()
    {
        $viewer = request()->user();
        $query = User::where('status', 'active')->whereNotNull('qr_code')->orderBy('name');

        if ($viewer->isStaffTeacherSupervisor()) {
            $query->whereHas('classroom', fn ($classroom) => $classroom->where('teacher_id', $viewer->id));
        } elseif ($viewer->isStudentEmployeeParticipant()) {
            $query->where('id', $viewer->id);
        }

        $users = $query->get();
        $html = $this->generateQrPrintHtml($users);

        return response()->streamDownload(
            fn () => print $html,
            'qr-codes-print-'.now()->format('Y-m-d').'.html',
            ['Content-Type' => 'text/html']
        );
    }

    private function generateQrPrintHtml($users)
    {
        $html = '<html><head><meta charset="UTF-8"><title>QR Codes Print</title>';
        $html .= '<style>body { font-family: Arial; margin: 10px; }';
        $html .= '.qr-item { display: inline-block; margin: 20px; text-align: center; page-break-inside: avoid; }';
        $html .= '.qr-item img { width: 150px; height: 150px; margin: 10px 0; }';
        $html .= '.qr-item p { margin: 5px 0; font-size: 12px; }';
        $html .= '@media print { .qr-item { page-break-inside: avoid; } }</style></head><body>';

        foreach ($users as $user) {
            $html .= '<div class="qr-item">';
            $html .= '<p><strong>'.htmlspecialchars($user->name).'</strong></p>';
            $html .= '<img src="'.self::dataUrlFor($user).'" alt="QR Code">';
            $html .= '<p>'.htmlspecialchars($user->email).'</p>';
            $html .= '</div>';
        }

        $html .= '</body></html>';

        return $html;
    }

    private function generateQrPng(User $user): string
    {
        $qrCode = new QrCode($user->qr_code ?? (string) $user->id);
        $writer = new PngWriter;

        return $writer->write($qrCode)->getString();
    }

    public static function dataUrlFor(User $user): string
    {
        $qrCode = new QrCode($user->qr_code ?? (string) $user->id);
        $writer = new PngWriter;

        return 'data:image/png;base64,'.base64_encode($writer->write($qrCode)->getString());
    }

    private function qrPayload(User $user): array
    {
        return [
            'qr_code' => $user->qr_code,
            'qr_image' => self::dataUrlFor($user),
            'user_id' => $user->id,
            'user_name' => $user->name,
        ];
    }

    private function ensureCanGenerateQr(User $user): void
    {
        $viewer = request()->user();

        if ($viewer->isSuperAdmin() || (int) $viewer->id === (int) $user->id) {
            return;
        }

        if ($viewer->isStaffTeacherSupervisor() && (int) $user->classroom?->teacher_id === (int) $viewer->id) {
            return;
        }

        abort(403, 'You are not allowed to generate this QR code.');
    }
}
