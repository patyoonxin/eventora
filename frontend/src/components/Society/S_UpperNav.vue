<template>
  <nav class="flex items-center justify-between px-6 py-4 bg-white shadow-lg ">
    <!-- Logo on the left -->
    <div class="flex items-center">
      <img src="@/assets/logo.png" alt="Logo" class="h-8 w-auto" />
    </div>

    <!-- Profile button on the right -->
    <div>
      <button
        class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors duration-200"
        title="Profile"
      >
        <i class="pi pi-user text-gray-700"></i>
      </button>
    </div>
  </nav>
</template>

<script>
export default {
  name: 'UpperNav',
}
</script>

<style scoped>
</style>
