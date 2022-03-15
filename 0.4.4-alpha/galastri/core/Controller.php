<?php

namespace galastri\core;

use galastri\core\Route;
use galastri\core\Debug;
use galastri\core\Parameters;
use galastri\modules\PerformanceAnalysis;

/**
 * This is the core controller class that needs to be inherited by the route controller.
 *
 * This class:
 * - Call for a __doBefore method if it exists in the route controller
 * - Call for the method that is defined by the child node's name
 *   - Call for the request method if it is defined in the route configuration.
 * - Call for a __doAfter method if it exists in the route controller
 * - Merge all the returning values of each of these methods in an array and mankes it available for
 *   the output scripts.
 */
abstract class Controller
{
    /**
     * Stores the state of the route controller. The execution of the route controller methods can
     * occur only one time and this property prevents multiple executions if the user, for example,
     * calls for the __construct() method in the route controller.
     *
     * When this property is false, it means that the methods can be called. When it is true, it
     * means that the methods won't be called again.
     *
     * @var bool
     */
    private bool $isConstructed = false;

    /**
     * Stores the data returned by the __doBefore method.
     *
     * @var array
     */
    private array $doBeforeData = [];

    /**
     * Stores the data returned by the __doAfter method.
     *
     * @var array
     */
    private array $doAfterData = [];

    /**
     * Stores the data returned by the route controller method.
     *
     * @var array
     */
    private array $controllerData = [];

    /**
     * Stores the data returned by the request method.
     *
     * @var array
     */
    private array $requestMethodData = [];

    /**
     * Stores the merged data from the $doBeforeData, $doAfterData, $controllerData and the
     * $requestMethodData properties.
     *
     * @var array
     */
    private array $resultData = [];

    /**
     * Stores the state of the execution of the next methods. When the user need to prevent the
     * executions of the method chain and skip to the end of the controller, it can to call the
     * skipNextMethods method to set this property as true. All the methods that come next to the
     * current method will be ignored.
     *
     * @var bool
     */
    private bool $skipNextMethods = false;

    /**
     * Stores an array that contains a file content, a content type and a file name. It is used when
     * the controller gets a file content to be returned to the File output. Only used by the File
     * output.
     *
     * @var array|null
     */
    private ?array $fileContents = null;

    /**
     * Declares that the route controller needs to have a main method declared too.
     *
     * @return array
     */
    abstract protected function main(): array;

    /**
     * Declares a base __doBefore method. This method isn't required to be in the route controller,
     * but when it exists it will always be executed before the route method. It serves to a
     * constructor like purpose.
     *
     * @return array
     */
    protected function __doBefore(): array
    {
        return [];
    }

    /**
     * Declares a base __doAfter method. This method isn't required to be in the route controller,
     * but when it exists it will always be executed after the route method.
     *
     * @return array
     */
    protected function __doAfter(): array
    {
        return [];
    }

    /**
     * The constructor method calls the chain of methods in order. It calls, first, the __doBefore
     * method, then the route method and the request method (if it exists), the __doAfter method
     * and, finally, merge all the returned values that will be used by the output scripts.
     *
     * This chain of methods is executed once, when the class is instantiated. After, the
     * $isConstructed property is defined as true, which prevents further executions.
     *
     * @return void
     */
    final public function __construct()
    {
        if (!$this->isConstructed) {
            $this->callDoBefore();
            $this->callControllerMethod();
            $this->callDoAfter();
            $this->mergeResults();

            $this->isConstructed = true;
        }
    }

    /**
     * This method calls for the __doBefore method and stores its returning array in the
     * $doBeforeData property.
     *
     * @return void
     */
    private function callDoBefore(): void
    {
        Debug::setBacklog();

        $this->doBeforeData = $this->__doBefore();

        PerformanceAnalysis::flush(PERFORMANCE_ANALYSIS_LABEL);
    }

    /**
     * This method calls for the route method, defined by the child node name in the route
     * resolving and stores its returning array in the $controllerData property. This execution will
     * only occur if the $skipNextMethods property is false.
     *
     * It also checks if there is a request method defined in the route configuration. If there is,
     * it calls it and stores its returning array in the $requestMethodData property. This execution
     * will only occur if the $skipNextMethods property is false.
     *
     * @return void
     */
    private function callControllerMethod(): void
    {
        Debug::setBacklog();

        if (!$this->skipNextMethods) {
            $controllerMethod = Route::getChildNodeName();

            $this->controllerData = $this->$controllerMethod();

            $requestMethod = Parameters::getRequest();
            if ($requestMethod and !$this->skipNextMethods) {
                $this->requestMethodData = $this->$requestMethod();
            }

            PerformanceAnalysis::flush(PERFORMANCE_ANALYSIS_LABEL);
        }
    }

    /**
     * This method calls for the __doAfter method and stores its returning array in the $doAfterData
     * property. This execution will only occur if the $skipNextMethods property is false.
     *
     * @return void
     */
    private function callDoAfter(): void
    {
        Debug::setBacklog();

        if (!$this->skipNextMethods) {
            $this->doAfterData = $this->__doAfter();

            PerformanceAnalysis::flush(PERFORMANCE_ANALYSIS_LABEL);
        }
    }

    /**
     * After all the method executions, this method merge all the resulting arrays in a unique array
     * $resultData property.
     *
     * @return void
     */
    private function mergeResults(): void
    {
        $this->resultData['controller'] = array_merge(
            $this->doBeforeData,
            $this->controllerData,
            $this->requestMethodData,
            $this->doAfterData,
            [
                'projectTitle' => $this->getProjectTitle(),
                'pageTitle' => $this->getPageTitle(),
            ]
        );

        $this->resultData['galastri'] = [
            'urlRoot' => '/'.Parameters::getUrlRoot(),
        ];

        PerformanceAnalysis::flush(PERFORMANCE_ANALYSIS_LABEL);
    }

    /**
     * This method sets the $skipNextMethods property to true. When this property is set to true,
     * all the following methods will be skipped to the end of the controller.
     *
     * @return void
     */
    final protected function skipNextMethods(): void
    {
        $this->skipNextMethods = true;
    }

    /**
     * This method return the merged array, result of the data returned by the methods called from
     * the route controller.
     *
     * @return array
     */
    final public function getResultData(): array
    {
        return $this->resultData;
    }

    /**
     * Works only for File output. Overwrites the 'downloadable' parameter if it is defined in the
     * route configuration. Sets if the browser will download the file instead of showing it in the
     * browser body.
     *
     * @param  bool $value                          Sets true or false to the 'downloadable'
     *                                              parameter.
     *
     * @return void
     */
    final protected function setDownloadable(bool $value): void
    {
        Parameters::setDownloadable($value);
    }

    /**
     * Gets the current value of the 'downloadable' parameter.
     *
     * @return bool|null
     */
    final public function getDownloadable(): ?bool
    {
        return Parameters::getDownloadable();
    }

    /**
     * Works only for File and View outputs. Overwrites the 'baseFolder' parameter if it is defined
     * in the route configuration. Sets a default base folder where the files or views are stored.
     *
     * @param  string $value                        The directory path location.
     *
     * @return void
     */
    final protected function setBaseFolder(string $value): void
    {
        Parameters::setBaseFolder($value);
    }

    /**
     * Gets the current value of the 'baseFolder' parameter.
     *
     * @return bool|null
     */
    final public function getBaseFolder(): ?string
    {
        return Parameters::getBaseFolder();
    }

    /**
     * Works only for View output. Overwrites the 'viewPath' parameter if it is defined in the route
     * configuration. Sets a specific view file for the View output.
     *
     * @param  string $value                        The path location of the view.
     *
     * @return void
     */
    final protected function setViewPath(string $value): void
    {
        Parameters::setViewPath($value);
    }

    /**
     * Gets the current value of the 'viewPath' parameter.
     *
     * @return null|string
     */
    final public function getViewPath(): ?string
    {
        return Parameters::getViewPath();
    }

    /**
     * Overwrites the 'projectTitle' parameter if it is defined in the project or route
     * configuration. Sets a project title.
     *
     * @param  string $value                        The project title.
     *
     * @return void
     */
    final protected function setProjectTitle(string $value): void
    {
        Parameters::setProjectTitle($value);
    }

    /**
     * Gets the current value of the 'projectTitle' parameter.
     *
     * @return null|string
     */
    final public function getProjectTitle(): ?string
    {
        return Parameters::getProjectTitle();
    }

    /**
     * Overwrites the 'pageTitle' parameter if it is defined in the route configuration. Sets a page
     * title.
     *
     * @param  string $value                        The page title.
     *
     * @return void
     */
    final protected function setPageTitle(string $value): void
    {
        Parameters::setPageTitle($value);
    }

    /**
     * Gets the current value of the 'pageTitle' parameter.
     *
     * @return null|string
     */
    final public function getPageTitle(): ?string
    {
        return Parameters::getPageTitle();
    }

    /**
     * Overwrites the 'output' parameter if it is defined in the route configuration. Sets the
     * output script name that will be called after the execution of the controller.
     *
     * @param  string $value                        The output script name.
     *
     * @return void
     */
    final protected function setOutput(string $value): void
    {
        Parameters::setOutput($value);
    }

    /**
     * Gets the current value of the 'output' parameter.
     *
     * @return null|string
     */
    final public function getOutput(): ?string
    {
        return Parameters::getOutput();
    }

    /**
     * Works only for View output. Overwrites the 'templateFile' parameter if it is defined in the
     * project or route configuration. Sets the template file for the View output.
     *
     * @param  string $value                        The path location of the template file.
     *
     * @return void
     */
    final protected function setTemplateFile(string $value): void
    {
        Parameters::setTemplateFile($value);
    }

    /**
     * Gets the current value of the 'templateFile' parameter.
     *
     * @return null|string
     */
    final public function getTemplateFile(): ?string
    {
        return Parameters::getTemplateFile();
    }

    /**
     * Works only for File output. Defines a file content that will be used instead of searching for
     * a file in the base folder.
     *
     * @param  string $fileContents                 The file contents that will be used.
     *
     * @param  string $contentType                  The content type of the file
     *
     * @param  string $fileName                     The file name, which will be used if the file is
     *                                              of downloadable type.
     *
     * @return void
     */
    final protected function setFileContents(string $fileContents, string $contentType, string $fileName): void
    {
        $this->fileContents = [$fileContents, $contentType, $fileName];
    }


    /**
     * Gets the current value of the 'fileContents' property.
     *
     * @return array
     */
    final public function getFileContents(): ?array
    {
        return $this->fileContents;
    }

    /**
     * Gets all the URL parameters defined based on the 'parameters' parameter in the route
     * configuration. If there is no URL parameters, it will return an empty array.
     *
     * @return array
     */
    final public function getUrlParameters(): array
    {
        return Parameters::getUrlParameters();
    }

    /**
     * Gets a specific URL parameter, by giving its tag name defined in the 'parameters' parameter
     * in the route configuration. If the tag name doesn't exists it returns null.
     *
     * @return null|string
     */
    final public function getUrlParameter(string $tagName): ?string
    {
        return Parameters::getUrlParameter($tagName) ?? null;
    }

    /**
     * Gets all the dynamic nodes defined in the route configuration.  If there is no dynamic nodes,
     * it will return an empty array.
     *
     * @return array
     */
    final public function getDynamicNodes(): array
    {
        return Route::getDynamicNodes();
    }

    /**
     * Gets a specific dynamic node, by giving its tag name defined in the route configuration. If
     * the tag name doesn't exists it returns null.
     *
     * @return null|string
     */
    final public function getDynamicNode(string $tagName): ?string
    {
        return Route::getDynamicNode($tagName) ?? null;
    }
}
