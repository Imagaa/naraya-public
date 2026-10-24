<?php

namespace App\Livewire\Sales;

use App\Notifications\AdminNotice;
use App\Support\Phone;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Sales "Akun": own photo, a WhatsApp change request (Admin approves), password. Name/email stay with Admin. */
class Account extends Component
{
    use WithFileUploads;

    public $photo;

    public string $phone = '';

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPassword_confirmation = '';

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $profile = auth()->user()->salesProfile;
        $this->phone = (string) ($profile?->pending_phone ?? $profile?->phone);
    }

    public function savePhoto(): void
    {
        $this->validate(['photo' => 'required|image|max:5120'], [
            'photo.required' => 'Pilih foto dulu.',
            'photo.image' => 'File harus berupa foto.',
            'photo.max' => 'Foto maksimal 5 MB.',
        ]);

        $profile = auth()->user()->salesProfile()->firstOrCreate([]);
        $old = $profile->photo_path;

        // A fresh path each time: the media host sits behind a CDN that would keep serving the old picture.
        $profile->update(['photo_path' => $this->photo->store('sales-photos/'.auth()->id(), 's3')]);

        \App\Support\Media::forget($old);

        $this->reset('photo');
        $this->statusMessage = 'Foto profil diperbarui.';
    }

    public function requestPhoneChange(): void
    {
        $profile = auth()->user()->salesProfile()->firstOrCreate([]);
        $digits = preg_replace('/\D/', '', $this->phone);

        if (strlen($digits) < 9 || strlen($digits) > 15) {
            $this->addError('phone', 'Nomor WhatsApp tidak valid.');

            return;
        }
        if (Phone::normalize($this->phone) === Phone::normalize($profile->phone)) {
            $this->addError('phone', 'Ini sudah nomor yang Anda pakai.');

            return;
        }

        // Stored the way Indonesians write it (08…); the live number only changes once Admin approves.
        $local = '0'.substr(Phone::normalize($this->phone), 2);
        $profile->update(['pending_phone' => $local, 'pending_phone_requested_at' => now()]);
        AdminNotice::toAdmins('Permintaan ganti nomor', auth()->user()->name." minta ganti nomor ke {$local}", route('admin.sales.index', ['antrean' => 'phone-change'], absolute: false), 'phone');
        $this->phone = $local;
        $this->statusMessage = 'Permintaan ganti nomor dikirim ke Admin.';
    }

    public function updatePassword(): void
    {
        $this->validate([
            'currentPassword' => 'required|string',
            'newPassword' => 'required|string|min:8|confirmed',
        ], [
            'currentPassword.required' => 'Kata sandi saat ini wajib diisi.',
            'newPassword.required' => 'Kata sandi baru wajib diisi.',
            'newPassword.min' => 'Kata sandi baru minimal 8 karakter.',
            'newPassword.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
        ]);

        if (! Hash::check($this->currentPassword, auth()->user()->password)) {
            $this->addError('currentPassword', 'Kata sandi saat ini salah.');

            return;
        }

        auth()->user()->update(['password' => Hash::make($this->newPassword)]);
        $this->reset(['currentPassword', 'newPassword', 'newPassword_confirmation']);
        $this->statusMessage = 'Kata sandi berhasil diganti.';
    }

    public function render()
    {
        return view('livewire.sales.account', [
            'user' => auth()->user()->load('salesProfile.area'),
        ])->layout('layouts.sales');
    }
}
