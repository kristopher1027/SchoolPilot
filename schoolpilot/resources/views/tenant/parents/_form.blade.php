@php($user = $guardian->user ?? null)
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <label class="text-sm font-medium text-gray-700">Full name
        <input name="name" required maxlength="255" value="{{ old('name', $user?->name) }}" class="mt-1 w-full rounded-md border-gray-300">
    </label>
    <label class="text-sm font-medium text-gray-700">Email address
        <input type="email" name="email" required maxlength="255" value="{{ old('email', $user?->email) }}" class="mt-1 w-full rounded-md border-gray-300">
    </label>
    <label class="text-sm font-medium text-gray-700">Phone number
        <input name="phone" maxlength="40" value="{{ old('phone', $user?->phone) }}" class="mt-1 w-full rounded-md border-gray-300">
    </label>
    <label class="text-sm font-medium text-gray-700">Relationship to student
        <input name="relationship" maxlength="100" placeholder="e.g. Mother, Father, Guardian" value="{{ old('relationship', $guardian->relationship ?? '') }}" class="mt-1 w-full rounded-md border-gray-300">
    </label>
    <label class="text-sm font-medium text-gray-700">Occupation
        <input name="occupation" maxlength="255" value="{{ old('occupation', $guardian->occupation ?? '') }}" class="mt-1 w-full rounded-md border-gray-300">
    </label>
    <label class="text-sm font-medium text-gray-700 sm:col-span-2">Address
        <textarea name="address" rows="3" maxlength="5000" class="mt-1 w-full rounded-md border-gray-300">{{ old('address', $guardian->address ?? '') }}</textarea>
    </label>
    <label class="text-sm font-medium text-gray-700 sm:col-span-2">Set / reset parent portal password
        <input type="password" name="portal_password" minlength="12" maxlength="255" autocomplete="new-password" class="mt-1 w-full rounded-md border-gray-300">
        <span class="mt-1 block text-xs font-normal text-gray-500">Optional. Setting a password of at least 12 characters activates this parent account. Share the password securely with the parent.</span>
    </label>
</div>
