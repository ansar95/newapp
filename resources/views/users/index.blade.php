<x-app-layout>
    <x-slot name="header">Users</x-slot>

    <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-maroon-50 text-left text-xs uppercase text-maroon-700">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3">Action</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-maroon-100">
                @foreach ($users as $user)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $user->name }}</td>
                        <td class="px-4 py-3">{{ $user->email }}</td>
                        <td class="px-4 py-3">{{ $user->role?->label() ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('users.role.update', $user) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <select name="role" class="rounded-md border-maroon-300 text-sm" @disabled($user->id === auth()->id())>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->value }}" @selected($user->role === $role)>{{ $role->label() }}</option>
                                    @endforeach
                                </select>
                                <button class="rounded-md bg-maroon-800 px-3 py-1.5 text-xs text-white" @disabled($user->id === auth()->id())>
                                    Save
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-maroon-100 px-4 py-3">{{ $users->links() }}</div>
    </div>
</x-app-layout>
