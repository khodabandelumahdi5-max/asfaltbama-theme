<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;

class ReviewController extends Controller
{
    public function index()
    {
        return view('admin.reviews', ['reviews' => Review::with('order.customer')->latest()->paginate(40)]);
    }

    /** Publishing requires the customer's explicit consent. */
    public function toggle(Review $review)
    {
        abort_unless($review->publish_consent, 422, 'مشتری اجازه انتشار نداده است.');
        $review->update(['is_published' => ! $review->is_published]);

        return back();
    }
}
