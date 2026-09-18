<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

// Step tracking
const currentStep = ref(1);

// State for showing the back confirmation modal
const showResetConfirmModal = ref(false);
const pendingStep = ref(null);

// Get today's date in YYYY-MM-DD format
const getTodayDateStr = () => {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const todayStr = getTodayDateStr();

// Form Data States
const form = ref({
    suburb: '',
    postcode: '',
    wasteType: null,
    binSize: null,
    deliveryDate: '',
    pickupDate: '',
    placement: '',
});

// Helper function to check if a given date string is Saturday (6) or Sunday (0)
const isWeekend = (dateStr) => {
    if (!dateStr) return false;
    const date = new Date(dateStr);
    const day = date.getDay();
    return day === 0 || day === 6;
};

// Helper function to add calendar days (+6 days for 7-day total rental, or +9 days for 10-day)
const addCalendarDays = (dateStr, daysToAdd) => {
    const d = new Date(dateStr);
    d.setDate(d.getDate() + daysToAdd);
    return d.toISOString().split('T')[0];
};

// Helper function to count ONLY working days (Monday-Friday) between start and end dates
const getWorkingDaysDifference = (startDateStr, endDateStr) => {
    if (!startDateStr || !endDateStr) return 0;
    let start = new Date(startDateStr);
    let end = new Date(endDateStr);
    if (end < start) return 0;

    let count = 0;
    let current = new Date(start);
    current.setDate(current.getDate() + 1); // skip delivery day itself
    while (current <= end) {
        const dayOfWeek = current.getDay();
        if (dayOfWeek !== 0 && dayOfWeek !== 6) {
            count++;
        }
        current.setDate(current.getDate() + 1);
    }
    return count;
};

const hasFilledData = computed(() => {
    return (
        form.value.suburb !== '' ||
        form.value.postcode !== '' ||
        form.value.wasteType !== null ||
        form.value.binSize !== null ||
        form.value.deliveryDate !== '' ||
        form.value.pickupDate !== '' ||
        form.value.placement !== ''
    );
});

const triggerBack = (targetStep) => {
    if (hasFilledData.value) {
        pendingStep.value = targetStep;
        showResetConfirmModal.value = true;
    } else {
        currentStep.value = targetStep;
    }
};

const confirmResetAndBack = () => {
    form.value = {
        suburb: '',
        postcode: '',
        wasteType: null,
        binSize: null,
        deliveryDate: '',
        pickupDate: '',
        placement: '',
    };
    currentStep.value = pendingStep.value;
    showResetConfirmModal.value = false;
    pendingStep.value = null;
};

const cancelReset = () => {
    showResetConfirmModal.value = false;
    pendingStep.value = null;
};

// Minimal delivery date starts strictly from TOMORROW
const minDeliveryDate = computed(() => {
    let d = new Date(todayStr);
    d.setDate(d.getDate() + 1); 
    while (d.getDay() === 0 || d.getDay() === 6) {
        d.setDate(d.getDate() + 1);
    }
    return d.toISOString().split('T')[0];
});

// Base included calendar days: Bin size >= 6 gets 10 days (+9), others get 7 days (+6)
const baseCalendarDaysOffset = computed(() => {
    return form.value.binSize?.id >= 6 ? 9 : 6;
});

// Standard included working days limit for pricing comparison (approx working days in 7 or 10 calendar days)
const baseIncludedWorkingDays = computed(() => {
    return form.value.binSize?.id >= 6 ? 7 : 5;
});

const minCollectionDate = computed(() => {
    if (!form.value.deliveryDate) return minDeliveryDate.value;
    return addCalendarDays(form.value.deliveryDate, baseCalendarDaysOffset.value);
});

// Watcher to automatically adjust collection date if delivery date changes (+6 or +9 calendar days)
watch(() => form.value.deliveryDate, (newDelivery) => {
    if (newDelivery) {
        if (isWeekend(newDelivery)) {
            form.value.deliveryDate = '';
            form.value.pickupDate = '';
            return;
        }
        form.value.pickupDate = addCalendarDays(newDelivery, baseCalendarDaysOffset.value);
    }
});

watch(() => form.value.pickupDate, (newPickup) => {
    if (newPickup && isWeekend(newPickup)) {
        form.value.pickupDate = '';
    }
});

// Computed dynamic total price based on fixed working day additions ($7.86/day) and weekend charges ($15.70)
const calculatedTotalPrice = computed(() => {
    if (!form.value.binSize) return 0;
    const basePrice = form.value.binSize.price;
    if (!form.value.deliveryDate || !form.value.pickupDate) return basePrice;

    const actualWorkingDays = getWorkingDaysDifference(form.value.deliveryDate, form.value.pickupDate);
    const standardLimit = baseIncludedWorkingDays.value;

    if (actualWorkingDays > standardLimit) {
        const extraWorkingDays = actualWorkingDays - standardLimit;
        let totalPrice = basePrice + (extraWorkingDays * 7.86);

        // Periksa apakah periode ekstensi melewati akhir pekan (Sabtu / Minggu)
        let start = new Date(form.value.deliveryDate);
        let end = new Date(form.value.pickupDate);
        let current = new Date(start);
        current.setDate(current.getDate() + baseCalendarDaysOffset.value + 1); // Mulai setelah hari standar inklusif

        let weekendPassed = false;
        while (current <= end) {
            const dayOfWeek = current.getDay();
            if (dayOfWeek === 0 || dayOfWeek === 6) {
                weekendPassed = true;
                break;
            }
            current.setDate(current.getDate() + 1);
        }

        if (weekendPassed) {
            totalPrice += 15.70;
        }

        return Math.round(totalPrice);
    }
    return basePrice;
});

const extraDaysCount = computed(() => {
    if (!form.value.deliveryDate || !form.value.pickupDate) return 0;
    const actualWorkingDays = getWorkingDaysDifference(form.value.deliveryDate, form.value.pickupDate);
    const standardLimit = baseIncludedWorkingDays.value;
    return actualWorkingDays > standardLimit ? actualWorkingDays - standardLimit : 0;
});

// VALIDASI COMPUTED UNTUK TOMBOL
const isStep1Valid = computed(() => {
    return form.value.suburb !== '' && form.value.wasteType !== null;
});

const isStep2Valid = computed(() => {
    return form.value.binSize !== null;
});

const isStep3Valid = computed(() => {
    return (
        form.value.deliveryDate !== '' &&
        form.value.pickupDate !== '' &&
        !isWeekend(form.value.deliveryDate) &&
        !isWeekend(form.value.pickupDate) &&
        form.value.placement !== '' &&
        form.value.pickupDate >= minCollectionDate.value
    );
});

const suburbData = [
    { name: 'Aberfoyle', postcode: '2350' },
    { name: 'Arding', postcode: '2358' },
    { name: 'Armidale', postcode: '2350' },
    { name: 'Backwater', postcode: '2350' },
    { name: 'Balala', postcode: '2358' },
    { name: 'Ben Lomond', postcode: '2365' },
    { name: 'Dangarsleigh', postcode: '2350' },
    { name: 'Glen Innes', postcode: '2370' },
    { name: 'Guyra', postcode: '2355' },
    { name: 'Tenterfield', postcode: '2372' },
    { name: 'Uralla', postcode: '2358' },
    { name: 'Yarrowyck', postcode: '2358' }
];

const updatePostcode = (selectedSuburb) => {
    const found = suburbData.find(s => s.name === selectedSuburb);
    if (found) {
        form.value.postcode = found.postcode;
    } else {
        form.value.postcode = '';
    }
};

const wasteTypes = [
    { 
        id: 'General', 
        name: 'General Waste', 
        desc: 'Household, office furniture, wood, metal, plastic, and clutter.', 
        isMaterialIcon: true,
        icon: 'house',
        allowed: 'Household items, furniture, wood, plastic, metal.',
        notAllowed: ['1. No Asbestos', '2. No Chemicals or Liquids', '3. No Tyres']
    },
    { 
        id: 'Green', 
        name: 'Green Waste', 
        desc: 'Garden clippings, grass, branches, leaves, and shrubs.', 
        isMaterialIcon: true,
        icon: 'nest_eco_leaf',
        allowed: 'Garden clippings, grass, branches, leaves, shrubs.',
        notAllowed: ['1. No Tree trunks > 300mm', '2. No Soil or Rocks', '3. No Food waste']
    },
    { 
        id: 'Heavy', 
        name: 'Heavy Waste', 
        desc: 'Bricks, concrete, tiles, clay, ceramic, and stones.', 
        isMaterialIcon: true,
        icon: 'garden_cart',
        allowed: 'Bricks, concrete, tiles, clay, ceramic, stones.',
        notAllowed: ['1. No Asbestos', '2. No Insulation', '3. No Tree roots']
    },
    { 
        id: 'Asbestos', 
        name: 'Asbestos', 
        desc: 'Specialized safe disposal for bonded asbestos materials.', 
        isMaterialIcon: true,
        icon: 'emergency_home',
        allowed: 'Bonded Asbestos sheeting (properly wrapped and sealed).',
        notAllowed: ['1. No Batteries', '2. No Gas bottles']
    }
];

const binSizes = [
    { id: 2, size: '2 m³', capacity: 'Mini Skip Bin - Ideal for small residential cleanups', daysLimit: '7 Days Rental Included', price: 320, maxWeight: '500 KG', overloadRate: '$0.16 / KG', img: '🗑️' },
    { id: 3, size: '3 m³', capacity: 'Small Skip - Equal to approx. 3 trailer loads', daysLimit: '7 Days Rental Included', price: 410, maxWeight: '750 KG', overloadRate: '$0.16 / KG', img: '🗑️' },
    { id: 4, size: '4 m³', capacity: 'Medium Skip - Great for household moving & renovations', daysLimit: '7 Days Rental Included', price: 520, maxWeight: '1,000 KG', overloadRate: '$0.18 / KG', img: '🚛' },
    { id: 6, size: '6 m³', capacity: 'Large Skip - Perfect for building sites & large cleanups', daysLimit: '10 Days Rental Included', price: 690, maxWeight: '1,500 KG', overloadRate: '$0.18 / KG', img: '🚚' },
    { id: 8, size: '8 m³', capacity: 'Extra Large Skip - Heavy commercial and bulky items', daysLimit: '10 Days Rental Included', price: 850, maxWeight: '2,000 KG', overloadRate: '$0.20 / KG', img: '🏗️' },
    { id: 10, size: '10 m³', capacity: 'Hook Bin - Industrial & major construction projects', daysLimit: '14 Days Rental Included', price: 1050, maxWeight: '2,500 KG', overloadRate: '$0.20 / KG', img: '🏭' },
    { id: 12, size: '12 m³', capacity: 'Maxi Hook Bin - Maximum capacity for massive volumes', daysLimit: '14 Days Rental Included', price: 1250, maxWeight: '3,000 KG', overloadRate: '$0.22 / KG', img: '🏭' },
];

const placementOptions = [
    { id: 'Driveway', label: 'Private Property / Driveway', note: 'Recommended: No council permit required.' },
    { id: 'Public', label: 'Public Land / Council Road', note: 'Note: Local council street permit may apply.' }
];

const selectedWasteObj = computed(() => {
    return wasteTypes.find(w => w.name === form.value.wasteType) || {};
});

const formatUSD = (val) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(val);
};

const formatDateReadable = (dateStr) => {
    if (!dateStr) return 'Not selected';
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateStr).toLocaleDateString('en-US', options);
};

const nextStep = () => {
    if (currentStep.value < 4) currentStep.value++;
};

const submitOrder = () => {
    alert('Thank you! Proceeding to checkout.');
};
</script>

<template>
    <Head title="New England Waste & Recycling - Skip Bins & Waste Solutions">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=emergency_home,garden_cart,house,nest_eco_leaf" />
    </Head>

    <div class="min-h-screen bg-slate-50 text-slate-800 font-sans flex flex-col justify-between relative">
        
        <!-- BACK CONFIRMATION POP-UP MODAL -->
        <div v-if="showResetConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 text-center space-y-4 border border-slate-100">
                <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mx-auto text-xl font-bold">
                    ⚠️
                </div>
                <h3 class="text-xl font-bold text-slate-900">Confirm Go Back</h3>
                <p class="text-sm text-slate-600 leading-relaxed">
                    Are you sure you want to go back? If you go back, the data you have entered will be lost.
                </p>
                <div class="flex space-x-3 pt-2">
                    <button @click="cancelReset" class="w-1/2 bg-slate-100 hover:bg-slate-200 text-slate-700 py-3 rounded-xl font-bold transition text-sm">
                        Cancel
                    </button>
                    <button @click="confirmResetAndBack" class="w-1/2 bg-red-600 hover:bg-red-700 text-white py-3 rounded-xl font-bold transition text-sm">
                        Yes, Go Back
                    </button>
                </div>
            </div>
        </div>

        <div>
            <!-- Top Navbar -->
            <header class="bg-white border-b border-slate-200 sticky top-0 z-40">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex justify-between items-center">
                    <div class="flex items-center space-x-3">
                        <div class="bg-emerald-600 text-white p-2.5 rounded-xl font-black text-xl">NEW</div>
                        <div>
                            <span class="text-lg font-bold tracking-tight text-slate-900 block leading-tight">New England</span>
                            <span class="text-xs font-semibold text-emerald-600 uppercase tracking-widest">Waste & Recycling</span>
                        </div>
                    </div>
                    <div class="flex items-center space-x-6">
                        <span class="text-xs font-bold bg-emerald-50 text-emerald-700 px-3 py-1.5 rounded-full border border-emerald-200 hidden md:inline">
                            Family Owned Since 1979
                        </span>
                        <Link href="/about" class="text-sm font-semibold text-slate-600 hover:text-emerald-600 transition">About Us</Link>
                    </div>
                </div>

                <!-- Secondary Green Navigation Bar (Centered) -->
                <nav class="bg-[#008037] text-white shadow-inner">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-12 flex items-center justify-center gap-8 sm:gap-12 text-sm font-bold tracking-wider">
                        <a href="#" class="hover:text-emerald-200 transition py-3">HOME</a>
                        <div class="relative group cursor-pointer flex items-center space-x-1 py-3 hover:text-emerald-200 transition">
                            <span>SERVICES</span>
                            <span class="text-xs">▾</span>
                        </div>
                        <a href="#" class="hover:text-emerald-200 transition py-3">NEWS</a>
                        <div class="relative group cursor-pointer flex items-center space-x-1 py-3 hover:text-emerald-200 transition">
                            <span>ABOUT US</span>
                            <span class="text-xs">▾</span>
                        </div>
                        <div class="relative group cursor-pointer flex items-center space-x-1 py-3 hover:text-emerald-200 transition">
                            <span>SERVICE AREAS</span>
                            <span class="text-xs">▾</span>
                        </div>
                    </div>
                </nav>
            </header>

            <!-- Hero Section with Parallax Background Image -->
            <section class="bg-slate-900 text-white py-20 px-4 text-center relative overflow-hidden bg-[url('https://newenglandwaste.com.au/wp-content/uploads/2021/02/NE-Waste-Home-Page-Image_Hook-Bin-1.jpg')] bg-fixed bg-cover bg-center">
                <div class="absolute inset-0 bg-slate-950/70"></div>
                <div class="max-w-4xl mx-auto space-y-4 relative z-10">
                    <span class="text-emerald-400 font-bold uppercase tracking-widest text-xs">Locally owned by the Lancaster family</span>
                    <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight">Reliable Waste Removal & Skip Bins</h1>
                    <p class="text-slate-300 max-w-2xl mx-auto text-base sm:text-lg leading-relaxed">
                        Serving Aberfoyle to Yarrowyck and all surrounding New England regions since 1979.
                    </p>
                </div>
            </section>

            <!-- Main Container -->
            <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 relative z-20 pb-16">
                
                <!-- Progress Bar Indicator -->
                <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 mb-8">
                    <div class="flex justify-between text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">
                        <span :class="{'text-emerald-600 font-bold': currentStep >= 1}">1. Suburb & Waste</span>
                        <span :class="{'text-emerald-600 font-bold': currentStep >= 2}">2. Choose Bin Size</span>
                        <span :class="{'text-emerald-600 font-bold': currentStep >= 3}">3. Schedule & Dates</span>
                        <span :class="{'text-emerald-600 font-bold': currentStep >= 4}">4. Booking Details</span>
                    </div>
                    <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                        <div class="bg-emerald-600 h-full transition-all duration-500 ease-out" :style="{ width: `${(currentStep / 4) * 100}%` }"></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <!-- Left Form Section -->
                    <div class="lg:col-span-2 space-y-6">
                        
                        <!-- STEP 1 -->
                        <div v-if="currentStep === 1" class="bg-white p-8 rounded-2xl shadow-sm border border-slate-200 space-y-6">
                            <h2 class="text-2xl font-bold text-slate-900">Select Suburb & Waste Type</h2>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-2">Suburb (Aberfoyle to Yarrowyck)</label>
                                    <select 
                                        v-model="form.suburb" 
                                        @change="updatePostcode(form.suburb)"
                                        class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:outline-none text-slate-700 font-medium bg-white transition-all hover:border-emerald-400"
                                    >
                                        <option value="" disabled>-- Select Suburb --</option>
                                        <option v-for="sub in suburbData" :key="sub.name" :value="sub.name">{{ sub.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-2">Postcode</label>
                                    <input 
                                        v-model="form.postcode" 
                                        type="text" 
                                        readonly 
                                        placeholder="Auto-filled"
                                        class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-slate-700 font-semibold"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-3">Select Waste & Service Solution</label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div 
                                        v-for="item in wasteTypes" 
                                        :key="item.id"
                                        @click="form.wasteType = item.name"
                                        :class="[
                                            'border-2 rounded-xl p-4 cursor-pointer transition-all duration-200 transform hover:-translate-y-1 hover:border-emerald-500 flex items-start space-x-3', 
                                            form.wasteType === item.name ? 'border-emerald-600 bg-emerald-50/40 scale-[1.02]' : 'border-slate-200 bg-white'
                                        ]"
                                    >
                                        <div v-if="item.isMaterialIcon" class="bg-slate-100 p-2 rounded-xl flex items-center justify-center text-emerald-700">
                                            <span class="material-symbols-outlined text-2xl leading-none">{{ item.icon }}</span>
                                        </div>
                                        <span v-else class="text-2xl">{{ item.icon }}</span>
                                        
                                        <div>
                                            <h4 class="font-bold text-slate-800 text-sm">{{ item.name }}</h4>
                                            <p class="text-xs text-slate-500 mt-1 leading-relaxed">{{ item.desc }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button 
                                @click="nextStep" 
                                :disabled="!isStep1Valid"
                                :class="[
                                    'w-full py-4 rounded-xl font-bold transition-all',
                                    isStep1Valid 
                                        ? 'bg-emerald-600 text-white hover:bg-emerald-700 active:scale-[0.99] cursor-pointer' 
                                        : 'bg-slate-200 text-slate-400 cursor-not-allowed'
                                ]"
                            >
                                Continue to Bin Selection &rarr;
                            </button>
                        </div>

                        <!-- STEP 2 -->
                        <div v-if="currentStep === 2" class="bg-white p-8 rounded-2xl shadow-sm border border-slate-200 space-y-6">
                            <div class="flex justify-between items-center">
                                <h2 class="text-2xl font-bold text-slate-900">Choose Bin Size</h2>
                                <button @click="triggerBack(1)" class="text-sm font-semibold text-emerald-600 hover:underline">&larr; Back</button>
                            </div>

                            <div class="space-y-4 max-h-[450px] overflow-y-auto pr-1">
                                <div 
                                    v-for="bin in binSizes" 
                                    :key="bin.id"
                                    @click="form.binSize = bin"
                                    :class="[
                                        'border-2 rounded-xl p-4 cursor-pointer transition-all duration-200 transform hover:-translate-y-0.5 hover:border-emerald-500 flex items-center justify-between', 
                                        form.binSize?.id === bin.id ? 'border-emerald-600 bg-emerald-50/40 scale-[1.01]' : 'border-slate-200 bg-white'
                                    ]"
                                >
                                    <div class="flex items-center space-x-4">
                                        <div class="text-3xl bg-slate-100 p-2.5 rounded-xl">{{ bin.img }}</div>
                                        <div>
                                            <h3 class="font-bold text-lg text-slate-800">{{ bin.size }}</h3>
                                            <p class="text-xs text-slate-500 font-medium">{{ bin.capacity }}</p>
                                            <span class="inline-block mt-1.5 text-xs bg-slate-100 text-slate-700 px-2.5 py-0.5 rounded-full font-semibold">Max: {{ bin.maxWeight }} | {{ bin.daysLimit }}</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xl font-extrabold text-emerald-600">{{ formatUSD(bin.price) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex space-x-4 pt-2">
                                <button @click="triggerBack(1)" class="w-1/3 bg-slate-100 text-slate-700 py-4 rounded-xl font-bold hover:bg-slate-200 transition-all">Back</button>
                                <button 
                                    @click="nextStep" 
                                    :disabled="!isStep2Valid"
                                    :class="[
                                        'w-2/3 py-4 rounded-xl font-bold transition-all',
                                        isStep2Valid 
                                            ? 'bg-emerald-600 text-white hover:bg-emerald-700 active:scale-[0.99] cursor-pointer' 
                                            : 'bg-slate-200 text-slate-400 cursor-not-allowed'
                                    ]"
                                >
                                    Continue to Schedule &rarr;
                                </button>
                            </div>
                        </div>

                        <!-- STEP 3 -->
                        <div v-if="currentStep === 3" class="bg-white p-8 rounded-2xl shadow-sm border border-slate-200 space-y-6">
                            <div class="flex justify-between items-center">
                                <h2 class="text-2xl font-bold text-slate-900">Delivery & Collection Dates</h2>
                                <button @click="triggerBack(2)" class="text-sm font-semibold text-emerald-600 hover:underline">&larr; Back</button>
                            </div>

                            <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl text-xs font-medium">
                                ℹ️ Note: Additional payment for extension days counts working days ($7.86/day) and weekend charges ($15.70) if applicable. Weekends are closed.
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-2">Delivery date</label>
                                    <input 
                                        v-model="form.deliveryDate" 
                                        type="date" 
                                        :min="minDeliveryDate"
                                        :class="['w-full px-4 py-3 rounded-xl border focus:ring-2 focus:ring-emerald-500 focus:outline-none text-slate-700 font-medium transition-all hover:border-emerald-400', isWeekend(form.deliveryDate) ? 'bg-slate-100 border-red-300 text-red-600 cursor-not-allowed' : 'border-slate-300']"
                                    />
                                    <span v-if="isWeekend(form.deliveryDate)" class="text-xs text-red-500 mt-1 block font-semibold">❌ Weekends are closed. Please pick a weekday.</span>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-2">Collection date</label>
                                    <input 
                                        v-model="form.pickupDate" 
                                        type="date" 
                                        :min="minCollectionDate"
                                        :class="['w-full px-4 py-3 rounded-xl border focus:ring-2 focus:ring-emerald-500 focus:outline-none text-slate-700 font-medium transition-all hover:border-emerald-400', isWeekend(form.pickupDate) ? 'bg-slate-100 border-red-300 text-red-600 cursor-not-allowed' : 'border-slate-300']"
                                    />
                                    <span v-if="isWeekend(form.pickupDate)" class="text-xs text-red-500 mt-1 block font-semibold">❌ Weekends are closed. Please pick a weekday.</span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-3">Placement Location</label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div 
                                        v-for="p in placementOptions" 
                                        :key="p.id"
                                        @click="form.placement = p.label"
                                        :class="[
                                            'border-2 rounded-xl p-4 cursor-pointer transition-all duration-200 transform hover:-translate-y-1 hover:border-emerald-500', 
                                            form.placement === p.label ? 'border-emerald-600 bg-emerald-50/40 scale-[1.02]' : 'border-slate-200 bg-white'
                                        ]"
                                    >
                                        <h4 class="font-bold text-slate-800 text-sm">{{ p.label }}</h4>
                                        <p class="text-xs text-slate-500 mt-1">{{ p.note }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex space-x-4">
                                <button @click="triggerBack(2)" class="w-1/3 bg-slate-100 text-slate-700 py-4 rounded-xl font-bold hover:bg-slate-200 transition-all">Back</button>
                                <button 
                                    @click="nextStep" 
                                    :disabled="!isStep3Valid"
                                    :class="[
                                        'w-2/3 py-4 rounded-xl font-bold transition-all',
                                        isStep3Valid 
                                            ? 'bg-emerald-600 text-white hover:bg-emerald-700 active:scale-[0.99] cursor-pointer' 
                                            : 'bg-slate-200 text-slate-400 cursor-not-allowed'
                                    ]"
                                >
                                    View Booking Details &rarr;
                                </button>
                            </div>
                        </div>

                        <!-- STEP 4 -->
                        <div v-if="currentStep === 4" class="bg-white p-8 rounded-2xl shadow-sm border border-slate-200 space-y-6">
                            
                            <div class="text-center space-y-2 bg-emerald-50/70 p-6 rounded-2xl border border-emerald-200">
                                <h3 class="text-xl sm:text-2xl font-extrabold text-emerald-700 flex items-center justify-center space-x-2">
                                    <span>✔</span>
                                    <span>Your total price is {{ formatUSD(calculatedTotalPrice) }} <span class="text-xs font-normal text-slate-600">(Include GST)</span></span>
                                </h3>
                                <p v-if="extraDaysCount > 0" class="text-xs font-semibold text-amber-700">
                                    Includes {{ extraDaysCount }} extra working extension day(s) ($7.86/day) and applicable weekend charges.
                                </p>
                                <div>
                                    <button 
                                        @click="submitOrder"
                                        class="mt-2 bg-lime-500 hover:bg-lime-600 active:scale-95 text-white font-extrabold px-8 py-3 rounded-xl transition-all text-lg uppercase tracking-wider"
                                    >
                                        » Book Now
                                    </button>
                                </div>
                            </div>

                            <h2 class="text-2xl font-extrabold text-lime-600 text-center pt-2">Your Booking Details</h2>

                            <div class="border border-slate-200 rounded-lg overflow-hidden text-sm">
                                <table class="w-full border-collapse">
                                    <tbody>
                                        <tr class="border-b border-slate-200">
                                            <td class="w-1/3 p-4 font-bold text-slate-800 bg-slate-50/50">Bin Size</td>
                                            <td class="w-2/3 p-4 font-semibold text-slate-900">{{ form.binSize?.size || 'Not selected' }}</td>
                                        </tr>
                                        <tr class="border-b border-slate-200 align-top">
                                            <td class="p-4 font-bold text-slate-800 bg-slate-50/50">Waste Type</td>
                                            <td class="p-4 space-y-2 text-slate-900">
                                                <p class="font-bold text-base">{{ form.wasteType || 'Not selected' }}</p>
                                                <div v-if="form.wasteType" class="text-xs space-y-1">
                                                    <p class="font-semibold text-emerald-700">Allowed:</p>
                                                    <p class="text-slate-600 pl-2">{{ selectedWasteObj.allowed }}</p>
                                                    <p class="font-semibold text-red-600 pt-1">Not Allowed:</p>
                                                    <ul class="pl-2 space-y-0.5 text-red-500 font-medium">
                                                        <li v-for="(item, idx) in selectedWasteObj.notAllowed" :key="idx">{{ item }}</li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="border-b border-slate-200">
                                            <td class="p-4 font-bold text-slate-800 bg-slate-50/50">Delivery Date</td>
                                            <td class="p-4 font-semibold text-slate-900">{{ formatDateReadable(form.deliveryDate) }}</td>
                                        </tr>
                                        <tr class="border-b border-slate-200">
                                            <td class="p-4 font-bold text-slate-800 bg-slate-50/50">Collection Date</td>
                                            <td class="p-4 font-semibold text-slate-900">{{ formatDateReadable(form.pickupDate) }}</td>
                                        </tr>
                                        <tr class="border-b border-slate-200">
                                            <td class="p-4 font-bold text-slate-800 bg-slate-50/50">Extra Working Days Extension</td>
                                            <td class="p-4 font-semibold text-slate-900">
                                                {{ extraDaysCount > 0 ? `${extraDaysCount} Extra Working Days` : 'None (Within Standard Limit)' }}
                                            </td>
                                        </tr>
                                        <tr class="border-b border-slate-200">
                                            <td class="p-4 font-bold text-slate-800 bg-slate-50/50">Max Weight</td>
                                            <td class="p-4 font-semibold text-slate-900">{{ form.binSize?.maxWeight || '-' }}</td>
                                        </tr>
                                        <tr class="border-b border-slate-200">
                                            <td class="p-4 font-bold text-slate-800 bg-slate-50/50">Overload Charge</td>
                                            <td class="p-4 font-semibold text-slate-900">{{ form.binSize?.overloadRate || '-' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="p-4 font-bold text-slate-800 bg-slate-50/50">Postcode</td>
                                            <td class="p-4 font-extrabold text-slate-900">{{ form.postcode || '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="flex justify-between items-center pt-4">
                                <button @click="triggerBack(3)" class="text-emerald-700 font-bold hover:underline text-sm flex items-center space-x-1">
                                    <span>« Go Back</span>
                                </button>
                                <button 
                                    @click="submitOrder" 
                                    class="bg-lime-500 hover:bg-lime-600 active:scale-95 text-white px-8 py-3 rounded-xl font-bold transition-all"
                                >
                                    Checkout
                                </button>
                            </div>
                        </div>

                    </div>

                    <!-- Right Sticky Summary Box -->
                    <div class="lg:col-span-1 space-y-6">
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 sticky top-36 space-y-4">
                            <h3 class="font-bold text-lg text-slate-900 border-b pb-3">Booking Summary</h3>
                            
                            <div class="space-y-3 text-sm text-slate-600">
                                <div class="flex justify-between">
                                    <span>Suburb:</span>
                                    <span class="font-semibold text-slate-800 text-right">{{ form.suburb ? `${form.suburb} (${form.postcode})` : 'Not selected' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Waste Type:</span>
                                    <span class="font-semibold text-slate-800 text-right">{{ form.wasteType || 'Not selected' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Bin Size:</span>
                                    <span class="font-semibold text-slate-800 text-right">{{ form.binSize ? form.binSize.size : 'Not selected' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Delivery:</span>
                                    <span class="font-semibold text-slate-800 text-right">{{ form.deliveryDate || 'Not selected' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Collection:</span>
                                    <span class="font-semibold text-slate-800 text-right">{{ form.pickupDate || 'Not selected' }}</span>
                                </div>
                                <div v-if="extraDaysCount > 0" class="flex justify-between text-amber-600 font-semibold text-xs pt-1 border-t border-slate-100">
                                    <span>Extra Working Days:</span>
                                    <span>+{{ extraDaysCount }} Days ($7.86/day)</span>
                                </div>
                            </div>

                            <hr class="border-slate-100 my-4">

                            <div class="flex justify-between items-center">
                                <span class="font-bold text-slate-900">Estimated Total:</span>
                                <span class="text-2xl font-black text-emerald-600">{{ formatUSD(calculatedTotalPrice) }}</span>
                            </div>

                            <div class="bg-emerald-50 text-emerald-900 p-4 rounded-xl text-xs leading-relaxed space-y-2 border border-emerald-200">
                                <p class="font-bold">Our Mission:</p>
                                <p class="text-emerald-800">To be a leader in resource recovery and sustainable waste solutions while maintaining a socially conscious approach.</p>
                            </div>
                        </div>
                    </div>

                </div>
            </main>
        </div>

        <!-- Footer Section -->
        <footer class="bg-slate-900 text-slate-400 border-t border-slate-800 pt-16 pb-8 px-4 sm:px-6 lg:px-8 mt-auto relative overflow-hidden bg-[url('https://newenglandwaste.com.au/wp-content/uploads/elementor/thumbs/Construction-Site-New-England-Waste-p3nlrc93g9z4iqnzkd1t9e3lymfyk2ts1lveaizs1s.png')] bg-cover bg-center">
            <div class="absolute inset-0 bg-slate-950/85"></div>

            <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-4 gap-8 mb-8 text-sm relative z-10">
                <div class="space-y-3 md:col-span-1">
                    <div class="flex items-center space-x-3">
                        <div class="bg-emerald-600 text-white p-2 rounded-lg font-black text-base">NEW</div>
                        <span class="text-white font-bold text-base tracking-tight">New England Waste</span>
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Providing dependable skip bins and sustainable waste management solutions across the New England region since 1979.
                    </p>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-3 text-xs uppercase tracking-wider">Service Areas</h4>
                    <ul class="space-y-2 text-xs">
                        <li>Aberfoyle to Armidale</li>
                        <li>Ben Lomond & Guyra</li>
                        <li>Uralla & Yarrowyck</li>
                        <li>All Surrounding Regions</li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-3 text-xs uppercase tracking-wider">Waste Solutions</h4>
                    <ul class="space-y-2 text-xs">
                        <li>General Household Waste</li>
                        <li>Green & Garden Waste</li>
                        <li>Heavy Building Materials</li>
                        <li>Asbestos & Specialized Bins</li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-3 text-xs uppercase tracking-wider">Family Owned</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Proudly managed by the Lancaster family, focused on community integrity and eco-friendly recycling practices.
                    </p>
                </div>
            </div>
            <div class="max-w-7xl mx-auto border-t border-slate-800/80 pt-6 flex flex-col sm:flex-row justify-between items-center text-xs text-slate-500 relative z-10">
                <p>&copy; 2026 New England Waste & Recycling. All rights reserved.</p>
                <div class="flex space-x-6 mt-4 sm:mt-0">
                    <span class="hover:text-slate-400 transition cursor-pointer">Privacy Policy</span>
                    <span class="hover:text-slate-400 transition cursor-pointer">Terms of Service</span>
                    <span class="hover:text-slate-400 transition cursor-pointer">Contact Support</span>
                </div>
            </div>
        </footer>
    </div>
</template>