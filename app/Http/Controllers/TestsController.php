<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestsController extends Controller
{
    public function getRestaurants()
    {
        // Example restaurant data with comments
        $restaurants = [
            [
                "name" => "SLZ",
                "description" => "Bar",
                "location" => "Maranhão",
                "img" => "URL",
                "menu" => [
                    [
                        "name" => "CHOPP",
                        "description" => "é um CHOPP",
                        "price" => 10
                    ]
                ],
                "likes" => 66,
                "comments" => [
                    [
                        "userName" => "John Doe",
                        "content" => "Tem Chopp, então ta bom!"
                    ],
                    [
                        "userName" => "Jane Smith",
                        "content" => "Tem Chopp, para mim ta bom!"
                    ]
                ]
            ],
            // Add more restaurants here
        ];

        return response()->json(['restaurants' => $restaurants]);
    }
}
