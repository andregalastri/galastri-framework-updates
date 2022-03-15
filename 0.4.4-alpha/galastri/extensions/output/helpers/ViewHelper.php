<?php

namespace galastri\extensions\output\helpers;

use galastri\core\Debug;
use galastri\extensions\Exception;
use galastri\modules\types\TypeString;

/**
 * This class is used in the template and view files and it is instanciated right before importing
 * the template file in an object called $galastri that can be used in any part of the template, the
 * view file, or in the other template parts that are imported into the main template.
 */
final class ViewHelper implements \Language
{
    /**
     * Stores the route controller data.
     *
     * @var array
     */
    private array $routeControllerData;

    /**
     * Stores an object that contains the view file path received by the View output.
     *
     * @var TypeString
     */
    private TypeString $viewFilePath;

    /**
     * The constructor requires the route controller array data and the view file path TypeString
     * object. This makes the data and the view available to be handle by this class.
     *
     * @param  mixed $routeControllerData           The array returned by the route controller.
     *
     * @param  mixed $viewFilePath                  An TypeString object that contains the view file
     *                                              path.
     *
     * @return void
     */
    public function __construct(array $routeControllerData, TypeString $viewFilePath)
    {
        $this->routeControllerData = $routeControllerData;
        $this->viewFilePath = $viewFilePath;
    }

    /**
     * This method return the data processed by the route controller. It can return all the data or
     * specific keys. If the key doesn't exist, it return null.
     *
     * @param  int|string ...$keys                  If empty, returns the entire array of the
     *                                              returning data of the route controller result.
     *                                              If an key is set, then the key is searched and
     *                                              its value is returned. If route controller data
     *                                              is a multidimensional array, each key passed as
     *                                              parameter refers to a level of the array.
     *
     *                                              - Example:
     *                                              Data returned by the route controller:
     *                                              [
     *                                                  'data1' => 'value1',
     *                                                  'data2' => [
     *                                                      'subdata1' => 'subvalue1',
     *                                                  ],
     *                                              ];
     *
     *                                              To get the value of the 'subdata1', just pass
     *                                              the key levels, from the main to its children:
     *
     *                                              echo $galastri->data('data2', 'subdata1');
     *
     *                                              Result will be: 'subvalue1'.
     *
     * @return mixed
     */
    public function getData(/*int|string*/ ...$keys)// : mixed
    {
        Debug::setBacklog();

        return $this->execData('controller', ...$keys);
    }

    /**
     * This method works in the same way of the getData method, but instead of return the value from
     * the controller, it returns internal data from the framework.
     *
     * @param  mixed $keys                          Works in the same way of the getData method, but
     *                                              refers to specific framework data.
     *
     * @return void
     */
    public function getFrameworkData(/*int|string*/ ...$keys)// : mixed
    {
        Debug::setBacklog();

        return $this->execData('galastri', ...$keys);
    }

    /**
     * This method works in the same way of the getData method, but instead of return the value, it
     * prints the data in the HTML. Because of that, it can't print an array or an object and will
     * thrown an exception when this occur. It also will throw an exception if no key is specified
     * as a parameter.
     *
     * @param  mixed $keys                          Works in the same way of the getData method,
     *                                              excepts that it will throw an error if no key is
     *                                              specified.
     *
     * @return void
     */
    public function printData(/*int|string*/ ...$keys) : void
    {
        Debug::setBacklog();

        $this->execData('controller', ...$keys);
    }

    /**
     * This method works in the same way of the getFrameworkData method, but instead of return the
     * value, it prints the data in the HTML. Because of that, it can't print an array or an object
     * and will thrown an exception when this occur. It also will throw an exception if no key is
     * specified as a parameter.
     *
     * @param  mixed $keys                          Works in the same way of the getFrameworkData
     *                                              method, excepts that it will throw an error if
     *                                              no key is specified.
     *
     * @return void
     */
    public function printFrameworkData(/*int|string*/ ...$keys) : void
    {
        Debug::setBacklog();

        $this->execPrint('galastri', ...$keys);
    }

    /**
     * Imports a external PHP file into the template allowing to use the $galastri object in that
     * part. This is the easiest way to make the $galastri object to be passed through the imported
     * files. This is the method used to import the view file also.
     *
     * If the file doesn't exist, no error will occur, the method will just return null.
     *
     * @param  mixed $path                          Can be the path of the PHP file that will be
     *                                              imported or the keyword 'view' to import the
     *                                              view file automatically.
     *
     * @return bool|null
     */
    public function import(string $path): ?bool
    {
        Debug::setBacklog();

        /**
         * This allows the galastri object to be used in the imported file.
         */
        $galastri = $this;

        /**
         * The parameter $path have two used in this method:
         *
         * 1. It can be equals to 'view' keyword, which means that the file that will be imported is
         *    the view file handled by the View output.
         */
        if ($path === 'view' and $this->viewFilePath->isNotNull()) {
            if ($this->viewFilePath->fileExists()) {
                return require($this->viewFilePath->realPath()->get());
            }

        /**
         * 2. It can be a path of a file, which has the starting point in the root of the project
         *    folder.
         */
        } else {
            $path = new TypeString($path);
            if ($path->fileExists()) {
                return include($path->realPath()->get());
            }
        }

        return null;
    }

    /**
     * Internal method that do the job of searching for the keys in the data returned the route
     * controller. This method is used by the getData, getFrameworkData and execPrint methods.
     *
     * @param  string $dataType                     Defines which array main key, from the
     *                                              controller, it will select. There are 2 possible
     *                                              values for now: galastri (refers to specific
     *                                              data from the framework) or controller (refers
     *                                              to the data from the controller)
     *
     * @param  mixed $keys                          Works in the same way of the getData method.
     *
     * @return void
     */
    private function execData(string $dataType, /*int|string*/ ...$keys)// : mixed
    {
        $routeControllerData = $this->routeControllerData[$dataType];

        if (empty($keys)) {
            return $routeControllerData;
        }

        foreach ($keys as $value) {
            if (isset($routeControllerData[$value])) {
                $routeControllerData = $routeControllerData[$value];
            } else {
                return null;
            }
        }

        return $routeControllerData;
    }

    /**
     * Internal method that do the job of printing the key value. This method is used by the data
     * and print methods.
     *
     * @param  string $dataType                     Defines which array main key, from the
     *                                              controller, it will select. There are 2 possible
     *                                              values for now: galastri (refers to specific
     *                                              data from the framework) or controller (refers
     *                                              to the data from the controller)
     *
     * @param  mixed $keys                          Works in the same way of the getData method.
     *
     * @return void
     */
    private function execPrint(string $dataType, /*int|string*/ ...$keys) : void
    {
        if (empty($keys)) {
            throw new Exception(self::VIEW_UNDEFINED_DATA_KEY[1], self::VIEW_UNDEFINED_DATA_KEY[0]);
        }

        $data = $this->execData($dataType, ...$keys);

        switch (gettype($data)) {
            case 'array':
            case 'object':
                throw new Exception(self::VIEW_INVALID_PRINT_DATA[1], self::VIEW_INVALID_PRINT_DATA[0]);
        }

        echo htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}
