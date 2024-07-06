<?php

namespace CoreFoundation\Services;

use CoreFoundation\Manipulators\ObjectMutable;
use CoreFoundation\Repositories\TestRepository;
use Log;

class TestService extends BaseService
{
    public function __construct(
        protected TestRepository $testRepository,

        /**
         * If your app is EDD then this will help you to manipulate data from events.
         *
         * @var ObjectMutable
         */
        protected ObjectMutable $objectMutable
    ) {
        // $this->eventPrefix = "";
        // parent::__construct();
    }

    public function index(array $filterable = [], array $relationship = ['user'])
    {
        $mutableData = $this->objectMutable->create([
            "filterable" => $filterable,
            "relationship" => $relationship,
        ]);

        // $this->eventDispatch(
        //     eventKey: "index.before",
        //     data: $mutableData
        // );
        // dd("asd");
        // $this->interceptorEventDispatch(
        //     eventKey: "jpt-asd.asdasd.asdaqweqwe.index.before",
        //     data: $mutableData
        // );
        // dd("asdasd");
        $filterable = $mutableData->get("filterable");
        $relationship = $mutableData->get("relationship");
                //wip test data
                $filters = [
                    "__in_id" => [2,4,5,1],
                    "__or_*" => [
                        "__eq_slug" => "rupesh",
                        "__in_id" => [1,2,3],

                        "__and_*" => [
                            "__eq_id" => 1,
                            "__like_slug" => ""
                        ],
                    ],
                    "__or_slug" => [
                        "__like_slug" => ""
                    ],
                    "__and_*" => [
                        "__eq_id" => 1,
                        "__like_slug" => ""
                    ],
                    "__eq_users.name" => ""
                ];
        Log::error("asdasd", [
            "test message",
            "data" => [
                $filterable
            ]
        ]);
        // dd($filterable, $relationship, $this->testRepository);
        $data = $this->testRepository->fetchAll($filterable, $relationship);
        dd($data);
    }

    public function resolveTo()
    {

    }
}
