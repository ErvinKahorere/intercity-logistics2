<template>
    <section id="hero" class="py-32 md:py-32 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div
                class="grid grid-cols-1 lg:grid-cols-2 gap-16 md:gap-16 items-start"
            >
                <div>
                    <h1
                        class="text-4xl md:text-4xl lg:text-7xl font-black text-gray-900 ibluec mb-8 mt-16 ">
                        Deliver Smarter, Faster, Easier
                        <span
                            class="text-transparent bg-clip-text bg-gradient-to-r from-blue-700 to-blue-950"
                        >
                         with InterCity Logistics </span
                        >️

                    </h1>

                    <h3  class="text-lg md:text-xl font-bold text-gray-900 mb-4 ibluec">Connecting You to Reliable Drivers Across Namibia</h3>


                    <p
                        class="text-base md:text-lg text-gray-600 leading-relaxed mb-16 md:mb-8 "
                    >
                        InterCity Logistics is the easiest way to find trusted drivers for your parcels, documents, personal items, and small cargo.
                    </p>

                    <hr>




                    <h5  class="text-lg md:text-xl font-bold text-gray-900 mb-2 ibluec"> </h5>

                        <p>   </p>

                    <div class="flex items-center gap-6 md:gap-12 py-6">
                        <!-- Left: Big Icon -->
                        <div class="flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-20 h-20 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>

                        <!-- Right: Text -->
                        <div>
                            <h5 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2 ibluec">
                                No complicated steps.
                            </h5>
                            <p class="text-gray-600 md:text-lg">
                                Simply search, view available drivers, and contact them directly to arrange your delivery.
                            </p>
                        </div>
                    </div>

                </div>


                <div
                    class="relative bg-gradient-to-br from-white-300 to-white-50/30 p-0 md:p-0 rounded-2xl mt-8 lg:mt-0"
                >


                    <!-- Background Image -->
                    <img :src="heroImage" alt="" class="w-full rounded-2xl shadow-2xl">

                    <!-- Form OVER the image -->
                    <!-- Form OVER the image -->
                    <div
                        class="absolute left-1/2 -translate-x-1/2 -bottom-10
               w-[92%] md:w-[80%]
               bg-white backdrop-blur-xl
               rounded-3xl shadow-2xl border border-white/30
               p-6 md:p-8"
                    >
                        <!-- Title -->

                        <div class="mb-4">
                            <h5 class="text-xl md:text-3xl font-bold text-gray-900 mb-2 ibluec">
                                <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-cyan-500">   Find a Driver </span>
                            </h5>
                        </div>


                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">

                            <!-- WHAT ARE YOU SENDING -->
                            <div>
<!--
                                <label class="block text-sm font-semibold text-gray-700 mb-3">
                <span class="inline-flex items-center justify-center
                           w-6 h-6 rounded-full bg-blue-600 text-white
                           font-bold text-sm mr-2">
                    A
                </span>
                                    What are you sending?
                                </label>
-->
                                <p>For</p>

                                <div class="relative">

                                    <!-- Icon -->
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                    📦
                </span>

                                    <select
                                        v-model="selectedPackage"
                                        class="w-full pl-10 pr-10 py-3 rounded-xl border border-gray-300
                           focus:border-blue-500 focus:ring-2 focus:ring-blue-200
                           bg-white text-gray-600 appearance-none"
                                    >
                                        <option value="" disabled selected hidden>Select item type…</option>
                                        <option value="Documents">Documents</option>
                                        <option value="Small Package">Small Package</option>
                                        <option value="Medium Item">Medium Item</option>
                                        <option value="Large Item">Large Item</option>
                                    </select>


                                </div>
                            </div>

                            <!-- WHERE TO PICK UP -->
                            <div>
<!--                                <label class="block text-sm font-semibold text-gray-700 mb-3">
                <span class="inline-flex items-center justify-center
                           w-6 h-6 rounded-full bg-blue-600 text-white
                           font-bold text-sm mr-2">
                    B
                </span>
                                    Where to Pick Up?
                                </label>-->

                                <p>From</p>

                                <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                    📍
                </span>

                                    <select
                                        v-model="pickupLocation"
                                        class="w-full pl-10 pr-10 py-3 rounded-xl border border-gray-300
                           focus:border-blue-500 focus:ring-2 focus:ring-blue-200
                           bg-white text-gray-600 appearance-none"
                                    >
                                        <option value="" disabled selected hidden>Select pickup…</option>
                                        <option>Windhoek</option>
                                        <option>Gobabis</option>
                                        <option>Swakopmund</option>
                                        <option>Walvis Bay</option>
                                        <option>Ongwendiva</option>
                                    </select>


                                </div>
                            </div>

                            <!-- WHERE TO DELIVER -->
                            <div>
<!--
                                <label class="block text-sm font-semibold text-gray-700 mb-3">
                <span class="inline-flex items-center justify-center
                           w-6 h-6 rounded-full bg-blue-600 text-white
                           font-bold text-sm mr-2">
                    C
                </span>
                                    Where to Deliver?
                                </label>
-->
                                <p>To</p>
                                <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                    🚚
                </span>

                                    <select
                                        v-model="deliveryLocation"
                                        class="w-full pl-10 pr-10 py-3 rounded-xl border border-gray-300
                           focus:border-blue-500 focus:ring-2 focus:ring-blue-200
                           bg-white text-gray-600 appearance-none"
                                    >
                                        <option value="" disabled selected hidden>Select destination…</option>
                                        <option>Windhoek</option>
                                        <option>Gobabis</option>
                                        <option>Swakopmund</option>
                                        <option>Walvis Bay</option>
                                        <option>Ongwendiva</option>
                                    </select>

                                </div>
                            </div>

                            <!-- BUTTON -->
                            <div class="flex items-end">
                                <a href="/find-Driver" class="w-full">
                                    <button
                                        @click="performSearch"
                                        class="w-full px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white
                           font-semibold rounded-xl shadow-lg transition-all duration-200"
                                    >
                                        Search Drivers
                                    </button>
                                </a>
                            </div>

                        </div>
                    </div>


                </div>
            </div>
        </div>
    </section>
</template>
<script setup>
import heroImage from '@/assets/images/image32.png';


</script>
