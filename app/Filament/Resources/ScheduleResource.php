<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScheduleResource\Pages;
use App\Models\Schedule;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ScheduleResource extends Resource
{
    protected static ?string $model = Schedule::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Jadwal';
    protected static ?string $navigationGroup = 'Manajemen';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'Jadwal';

    public static function form(Form $form): Form
    {
        $kelasOptions = collect(['7A','7B','7C','7D','7E','7F','7G','8A','8B','8C','8D','8E','8F','8G','9A','9B','9C','9D','9E','9F','9G'])
            ->mapWithKeys(fn($k) => [$k => $k])->toArray();

        return $form->schema([
            Forms\Components\Section::make('Detail Jadwal')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('teacher_id')
                        ->label('Guru Pengajar')
                        ->relationship('teacher', 'name')
                        ->searchable()
                        ->required(),
                    Forms\Components\Select::make('class_id')
                        ->label('Kelas')
                        ->relationship('schoolClass', 'id')
                        ->required()
                        ->searchable(),
                    Forms\Components\Select::make('subject_id')
                        ->label('Mata Pelajaran')
                        ->relationship('subject', 'name')
                        ->required()
                        ->searchable(),
                    Forms\Components\Select::make('day_of_week')
                        ->label('Hari')
                        ->options([
                            'SENIN' => 'Senin', 'SELASA' => 'Selasa', 'RABU' => 'Rabu',
                            'KAMIS' => 'Kamis', 'JUMAT' => 'Jumat', 'SABTU' => 'Sabtu',
                        ])
                        ->required(),
                    Forms\Components\Select::make('time_slot_id')
                        ->label('Jam Pelajaran Ke-')
                        ->relationship('timeSlot', 'id')
                        ->required(),
                    Forms\Components\TextInput::make('keterangan')
                        ->label('Keterangan / Catatan Tambahan')
                        ->maxLength(255),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('day_of_week')->label('Hari')->sortable()
                    ->sortable(query: function ($query, $direction) {
                        return $query->orderByRaw('FIELD(day_of_week, "SENIN","SELASA","RABU","KAMIS","JUMAT","SABTU") '.$direction);
                    }),
                Tables\Columns\TextColumn::make('timeSlot.id')->label('Jam Ke')->sortable(),
                Tables\Columns\TextColumn::make('timeSlot.start_time')->label('Waktu')->time('H:i')->sortable(),
                Tables\Columns\BadgeColumn::make('schoolClass.id')->label('Kelas')->sortable(),
                Tables\Columns\TextColumn::make('subject.name')->label('Mapel')->searchable(),
                Tables\Columns\TextColumn::make('teacher.name')->label('Guru')->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('class_id')
                    ->label('Kelas')
                    ->relationship('schoolClass', 'id'),
                Tables\Filters\SelectFilter::make('day_of_week')
                    ->label('Hari')
                    ->options(['SENIN'=>'Senin','SELASA'=>'Selasa','RABU'=>'Rabu','KAMIS'=>'Kamis','JUMAT'=>'Jumat','SABTU'=>'Sabtu']),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSchedules::route('/'),
            'calendar' => Pages\ScheduleCalendar::route('/calendar'),
        ];
    }
}
