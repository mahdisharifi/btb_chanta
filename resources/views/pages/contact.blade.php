@extends('layouts.app')

@section('content')
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">تماس با ما</h1>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold mb-4">اطلاعات تماس</h2>
                <ul class="space-y-4 text-gray-600">
                    <li class="flex items-center gap-3"><span>📞</span> 021-55915347</li>
                    <li class="flex items-center gap-3"><span>📱</span> 09355921203</li>
                    <li class="flex items-center gap-3"><span>✉️</span> info@chanta.ir</li>
<!--                    <li class="flex items-center gap-3"><span>📍</span> شهرری،میدان شهید غیبی،خیابان 8 متری انقلاب،کوچه شهید تمجیدی،پلاک 12</li>-->
                </ul>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold mb-4">فرم تماس</h2>
                <form class="space-y-4">
                    <input type="text" placeholder="نام و نام خانوادگی" class="w-full border-gray-300 rounded-lg">
                    <input type="email" placeholder="ایمیل" class="w-full border-gray-300 rounded-lg">
                    <textarea rows="4" placeholder="پیام شما..." class="w-full border-gray-300 rounded-lg"></textarea>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-semibold transition">ارسال</button>
                </form>
            </div>
        </div>
    </div>
@endsection
