<?php

namespace Bga\Games\SuperTeam\Algorithm;

class BipartiteMatcher
{
    /**
     * @param array<int, list<string>> $workerCapabilities  workerId => list of job keys that worker can fill
     * @param array<string, int> $jobDemand                 jobKey => number of workers required
     */
    public function canSatisfyDemand(array $workerCapabilities, array $jobDemand): bool
    {
        $sourceNode = new FlowGraphNode();
        $sinkNode = new FlowGraphNode();

        $jobNodes = [];
        foreach ($jobDemand as $jobKey => $amount)
        {
            $jobNodes[$jobKey] = new FlowGraphNode();
        }

        foreach ($workerCapabilities as $jobs)
        {
            $workerNode = new FlowGraphNode();
            $edge = Edge::createEdge(1, $sourceNode, $workerNode);
            $sourceNode->outEdges[] = $edge;
            $workerNode->outEdges[] = $edge->getReverse();

            foreach ($jobs as $jobKey)
            {
                if (!isset($jobNodes[$jobKey])) continue;

                $jobEdge = Edge::createEdge(1, $workerNode, $jobNodes[$jobKey]);
                $workerNode->outEdges[] = $jobEdge;
                $jobNodes[$jobKey]->outEdges[] = $jobEdge->getReverse();
            }
        }

        foreach ($jobDemand as $jobKey => $amount)
        {
            $jobNode = $jobNodes[$jobKey];
            $edge = Edge::createEdge($amount, $jobNode, $sinkNode);
            $jobNode->outEdges[] = $edge;
            $sinkNode->outEdges[] = $edge->getReverse();
        }

        return $this->getMaxFlow($sourceNode, $sinkNode) === array_sum($jobDemand);
    }

    private function getMaxFlow(FlowGraphNode $sourceNode, FlowGraphNode $sinkNode) : int
    {
        do
        {
            $visited = [];
            $augmentedPath = $this->findAugmentingPath($sourceNode, $sinkNode, $visited) ?? [];

            $minCapacity = PHP_INT_MAX;
            foreach ($augmentedPath as $edge)
            {
                $minCapacity = min($minCapacity, $edge->getRemainingCapacity());
            }

            foreach ($augmentedPath as $edge)
            {
                $edge->addFlow($minCapacity);
                $edge->getReverse()->addFlow(-$minCapacity);
            }
        } while (count($augmentedPath) > 0);

        $maxFlow = 0;
        foreach($sourceNode->outEdges as $edge)
        {
            $maxFlow += $edge->getFlow();
        }

        return $maxFlow;
    }

    private function findAugmentingPath(FlowGraphNode $node, FlowGraphNode $sink, array &$visited): array | null
    {
        $visited[spl_object_id($node)] = true;

        if ($node === $sink)
            return [];

        foreach ($node->outEdges as $edge)
        {
            $next = $edge->getEndNode();
            if ($edge->getRemainingCapacity() > 0 && !isset($visited[spl_object_id($next)]))
            {
                $path = $this->findAugmentingPath($next, $sink, $visited);
                if ($path !== null)
                {
                    array_unshift($path, $edge);
                    return $path;
                }
            }
        }

        return null; // no path found from this node
    }
}

class FlowGraphNode
{
    public array $outEdges = [];
}

class Edge
{
    private readonly FlowGraphNode $startNode;
    private readonly FlowGraphNode $endNode;

    private readonly int $capacity;
    private int $flow = 0;

    private Edge $reverse;

    private function __construct(int $capacity, FlowGraphNode $startNode, FlowGraphNode $endNode)
    {
        $this->capacity = $capacity;
        $this->startNode = $startNode;
        $this->endNode = $endNode;
    }

    public function getCapacity() : int
    {
        return $this->capacity;
    }

    public function addFlow(int $flow) : void
    {
        $this->flow += $flow;
    }

    public function getFlow() : int
    {
        return $this->flow;
    }

    public function getRemainingCapacity() : int
    {
        return $this->capacity - $this->flow;
    }

    public function getStartNode() : FlowGraphNode
    {
        return $this->startNode;
    }

    public function getEndNode() : FlowGraphNode
    {
        return $this->endNode;
    }

    public function getReverse() : Edge
    {
        return $this->reverse;
    }

    public static function createEdge(int $capacity, FlowGraphNode $startNode, FlowGraphNode $endNode) : Edge
    {
        $edge = new Edge($capacity, $startNode, $endNode);
        $residual = new Edge(0, $endNode, $startNode);

        $edge->reverse = $residual;
        $residual->reverse = $edge;

        return $edge;
    }
}