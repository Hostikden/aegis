<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Section::make('Данные сотрудника')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('ФИО Сотрудника')
                        ->required(),

                    Forms\Components\TextInput::make('email')
                        ->label('Email (Логин для входа)')
                        ->email()
                        ->unique(ignoreRecord: true)
                        ->required(),

                    Forms\Components\Select::make('role')
                        ->label('Роль в производстве / Должность')
                        ->options([
                            'admin' => '👑 Администратор системы',
                            'director' => '📊 Директор предприятия',
                            'manager' => '👨‍💼 Начальник цеха / Менеджер',
                            'technologist' => '📐 Технолог (Конструктор BOM)',
                            'storekeeper' => '📦 Заведующий складом',
                            'worker' => '🛠️ Рабочий (Оператор / Станочник)',
                        ])
                        ->required(),

                    Forms\Components\TextInput::make('password')
                        ->label('Пароль')
                        ->password()
                        // Пароль обязателен только при создании нового сотрудника
                        ->required(fn (string $context): bool => $context === 'create')
                        ->dehydrated(fn ($state) => filled($state)),
                ])->columns(2),
        ]);
}


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
{
    // Изменять список сотрудников и раздавать роли может ТОЛЬКО админ
    return auth()->user()->isAdmin();
}


}
