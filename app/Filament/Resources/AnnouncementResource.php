<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AnnouncementResource\Pages;
use App\Filament\Resources\AnnouncementResource\RelationManagers;
use App\Models\Announcement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Input Judul (Lebar Penuh)
                \Filament\Forms\Components\TextInput::make('title')
                    ->label('Judul Pengumuman')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                // Input Isi Konten (Rich Editor: Bisa Bold, Italic, List)
                \Filament\Forms\Components\RichEditor::make('content')
                    ->label('Isi Pengumuman')
                    ->required()
                    ->columnSpanFull(),

                // Input Upload Gambar (Disimpan di folder 'announcements')
                \Filament\Forms\Components\FileUpload::make('image')
                    ->label('Banner Gambar')
                    ->image()
                    ->directory('announcements')
                    ->columnSpanFull(),

                // Toggle Aktif/Tidak
                \Filament\Forms\Components\Toggle::make('is_active')
                    ->label('Terbitkan Sekarang?')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // 1. Menampilkan Gambar (Thumbnail)
                \Filament\Tables\Columns\ImageColumn::make('image')
                    ->label('Banner'),

                // 2. Menampilkan Judul (Bisa dicari)
                \Filament\Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                // 3. Status Tayang (Icon Centang/Silang)
                \Filament\Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Aktif'),

                // 4. Tanggal Dibuat
                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Tables\Actions\EditAction::make(),
                \Filament\Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Tables\Actions\BulkActionGroup::make([
                    \Filament\Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnnouncements::route('/'),
            'create' => Pages\CreateAnnouncement::route('/create'),
            'edit' => Pages\EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
