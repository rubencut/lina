<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class QrCodeController extends Controller
{
    /**
     * Generate QR code for a user.
     */
    public function generateUserQr(User $user)
    {
        try {
            if (!$user->qr_code) {
                $user->update(['qr_code' => Str::uuid()->toString()]);
            }

            $qrCodeData = $this->getQrCodeDataUrl($user);

            return response()->json([
                'qr_code' => $user->qr_code,
                'qr_image' => $qrCodeData,
                'user_id' => $user->id,
                'user_name' => $user->name,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get QR code for display.
     */
    public function getQrCode(User $user)
    {
        $this->ensureCanViewUser($user);

        if (!$user->qr_code) {
            return response()->json(['error' => 'QR code not generated'], 404);
        }

        try {
            $qrCodeData = $this->getQrCodeDataUrl($user);

            return response()->json([
                'qr_code' => $user->qr_code,
                'qr_image' => $qrCodeData,
                'user_id' => $user->id,
                'user_name' => $user->name,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Scan QR code and mark attendance.
     */
    public function scanQrCode(Request $request)
    {
        $validated = $request->validate([
            'qr_code' => 'required|string',
            'classroom_id' => 'required|integer|exists:classrooms,id',
        ]);

        try {
            $user = User::where('qr_code', $validated['qr_code'])->first();

            if (!$user) {
                return response()->json(['error' => 'Invalid QR code'], 404);
            }

            // Check for duplicate attendance (same user, classroom, date)
            $existing = \App\Models\Attendance::where('user_id', $user->id)
                ->where('classroom_id', $validated['classroom_id'])
                ->whereDate('date', now())
                ->first();

            if ($existing) {
                return response()->json([
                    'error' => 'Attendance already marked for today',
                    'message' => "User {$user->name} already marked present at {$existing->time_in}",
                ], 409);
            }

            // Create attendance record
            $attendance = \App\Models\Attendance::create([
                'user_id' => $user->id,
                'classroom_id' => $validated['classroom_id'],
                'date' => now()->toDateString(),
                'time_in' => now()->toTimeString(),
                'status' => 'Present',
                'recorded_by' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Attendance marked successfully',
                'user' => $user->name,
                'attendance' => $attendance->load(['user', 'classroom']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Verify QR code validity.
     */
    public function verifyQrCode(Request $request)
    {
        $validated = $request->validate([
            'qr_code' => 'required|string',
        ]);

        $user = User::where('qr_code', $validated['qr_code'])->first();

        if (!$user) {
            return response()->json(['valid' => false, 'message' => 'Invalid QR code'], 404);
        }

        return response()->json([
            'valid' => true,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'status' => $user->status,
        ]);
    }

    /**
     * Download QR code as PNG.
     */
    public function downloadQrCode(User $user)
    {
        $this->ensureCanViewUser($user);

        if (!$user->qr_code) {
            return response()->json(['error' => 'QR code not generated'], 404);
        }

        try {
            $png = $this->generateQrPng($user);

            return response()->streamDownload(function () use ($png) {
                echo $png;
            }, "qr-code-{$user->id}.png", ['Content-Type' => 'image/png']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Print QR codes for all users.
     */
    public function printAllQrCodes()
    {
        try {
            $users = User::where('status', 'active')
                ->whereNotNull('qr_code')
                ->get();

            $html = $this->generateQrPrintHtml($users);

            return response()->streamDownload(
                function () use ($html) {
                    echo $html;
                },
                'qr-codes-print-' . now()->format('Y-m-d') . '.html',
                ['Content-Type' => 'text/html']
            );
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
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
            try {
                $qrImage = $this->getQrCodeDataUrl($user);
                $html .= '<div class="qr-item">';
                $html .= '<p><strong>' . htmlspecialchars($user->name) . '</strong></p>';
                $html .= '<img src="' . $qrImage . '" alt="QR Code">';
                $html .= '<p>' . $user->email . '</p>';
                $html .= '</div>';
            } catch (\Exception $e) {
                // Skip user if QR generation fails
                continue;
            }
        }

        $html .= '</body></html>';

        return $html;
    }

    private function generateQrPng(User $user): string
    {
        $qrCode = new QrCode($user->qr_code ?? (string) $user->id);
        $writer = new PngWriter();

        return $writer->write($qrCode)->getString();
    }

    private function getQrCodeDataUrl(User $user): string
    {
        return 'data:image/png;base64,' . base64_encode($this->generateQrPng($user));
    }
}
