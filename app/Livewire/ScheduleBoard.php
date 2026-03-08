<?php

namespace App\Livewire;

use Livewire\Component;

use \App\Models\SchoolClass;
use \App\Models\TimeSlot;
use \App\Models\Schedule;

class ScheduleBoard extends Component
{
    public $classes;
    public $slots;
    public $days = ['SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU'];
    public $matrix = [];
    public $selectedClass = null;

    public function mount()
    {
        $this->classes = SchoolClass::orderBy('id')->get();
        $this->slots = TimeSlot::orderBy('id')->get();
        $this->selectedClass = $this->classes->first()->id ?? null;
        $this->loadMatrix();
    }

    public function updatedSelectedClass()
    {
        $this->loadMatrix();
    }

    public function loadMatrix()
    {
        // Tarik jadwal dari Database khusus satu kelas (biar GUI tidak tumpah-tumpah)
        $query = Schedule::with(['subject', 'teacher', 'timeSlot'])
            ->where('class_id', $this->selectedClass)
            ->get();

        // Inisialisasi Matriks kosong
        $this->matrix = [];
        foreach ($this->days as $day) {
            foreach ($this->slots as $slot) {
                $this->matrix[$day][$slot->id] = null;
            }
        }

        // Isi Matriks dengan Object Schedule (Jika ada)
        foreach ($query as $sch) {
            $this->matrix[$sch->day_of_week][$sch->time_slot_id] = $sch;
        }
    }

    public function moveSchedule($scheduleId, $newDay, $newSlotId)
    {
        $schedule = Schedule::find($scheduleId);
        if (!$schedule) return;

        // Cek apakah slot tujuan sudah diisi jadwal lain dalam kelas yang sama
        $existing = Schedule::where('class_id', $this->selectedClass)
            ->where('day_of_week', $newDay)
            ->where('time_slot_id', $newSlotId)
            ->first();

        if ($existing) {
            // Jika ada isinya, kita swap (tukar tempat)
            $oldDay = $schedule->day_of_week;
            $oldSlot = $schedule->time_slot_id;

            $existing->update([
                'day_of_week' => $oldDay,
                'time_slot_id' => $oldSlot,
            ]);
        }

        // Pindahkan jadwal yang di-drag
        $schedule->update([
            'day_of_week' => $newDay,
            'time_slot_id' => $newSlotId,
        ]);

        // Kirim Notifikasi Sukses
        \Filament\Notifications\Notification::make()
            ->title('Jadwal Berhasil Dipindahkan')
            ->success()
            ->send();

        // Segarkan Matriks UI
        $this->loadMatrix();
    }

    public function render()
    {
        return view('livewire.schedule-board');
    }
}
