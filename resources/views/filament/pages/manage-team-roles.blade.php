<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                    Role Information and Details
                </h3>
                <div class="mt-2 max-w-xl text-sm text-gray-500 dark:text-gray-400">
                    <p>Understanding the role permissions in your team:</p>
                </div>
                <div class="mt-5">
                    <div class="space-y-3">
                        <div class="flex items-center space-x-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                Viewer
                            </span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">Read-only access to team data</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-700 dark:text-blue-200">
                                Member
                            </span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">Can edit own entries and create new ones</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-200">
                                Admin
                            </span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">Full team management and can invite users</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                Super Admin
                            </span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">Full control, can create teams and access admin area</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                    Team Members
                </h3>
                <div class="mt-2 max-w-xl text-sm text-gray-500 dark:text-gray-400">
                    <p>Manage roles and permissions for your team members.</p>
                </div>
                <div class="mt-5">
                    {{ $this->table }}
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>