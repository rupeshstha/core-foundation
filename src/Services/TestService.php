<?php

namespace CoreFoundation\Services;

use CoreFoundation\Manipulators\ObjectMutable;
use CoreFoundation\Repositories\TestRepository;

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
            'filterable' => $filterable,
            'relationship' => $relationship,
        ]);

        // $this->eventDispatch(
        //     eventKey: "index.before",
        //     data: $mutableData
        // );
        // dd("asd");
        $this->interceptorEventDispatch(
            eventKey: 'jpt-asd.asdasd.asdaqweqwe.index.before',
            data: $mutableData
        );
        dd('asdasd');
        $filterable = $mutableData->get('filterable');
        $relationship = $mutableData->get('relationship');
        dd($filterable, $relationship, $this->testRepository);
        $data = $this->testRepository->fetchAll($filterable, $relationship);
        dd($data);
    }

    public function resolveTo() {}
}
